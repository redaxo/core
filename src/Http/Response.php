<?php

namespace Redaxo\Core\Http;

use DateTimeInterface;
use Ramsey\Http\Range\Exception\HttpRangeException;
use Ramsey\Http\Range\UnitFactory;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

use function function_exists;
use function in_array;
use function ini_get;
use function is_resource;
use function sprintf;

use const PHP_SAPI;

final class Response
{
    public const string HTTP_OK = '200 OK';
    public const string HTTP_PARTIAL_CONTENT = '206 Partial Content';
    public const string HTTP_MOVED_PERMANENTLY = '301 Moved Permanently';
    public const string HTTP_NOT_MODIFIED = '304 Not Modified';
    public const string HTTP_MOVED_TEMPORARILY = '307 Temporary Redirect';
    public const string HTTP_BAD_REQUEST = '400 Bad Request';
    public const string HTTP_NOT_FOUND = '404 Not Found';
    public const string HTTP_FORBIDDEN = '403 Forbidden';
    public const string HTTP_UNAUTHORIZED = '401 Unauthorized';
    public const string HTTP_RANGE_NOT_SATISFIABLE = '416 Range Not Satisfiable';
    public const string HTTP_INTERNAL_ERROR = '500 Internal Server Error';
    public const string HTTP_SERVICE_UNAVAILABLE = '503 Service Unavailable';

    private static string $httpStatus = self::HTTP_OK;
    private static bool $sentLastModified = false;
    private static bool $sentEtag = false;
    private static bool $sentContentType = false;
    private static bool $sentCacheControl = false;
    private static bool $closeConnection = false;
    /** @var array<string, string> */
    private static array $additionalHeaders = [];
    /** @var list<array{file: string, type: string, mimeType: string}> */
    private static array $preloadFiles = [];
    private static string $nonce = '';

    /** Sets the HTTP Status code. */
    public static function setStatus(string $httpStatus): void
    {
        if (str_contains($httpStatus, "\n")) {
            throw new InvalidArgumentException('Invalid http-status "' . $httpStatus . '", contains newlines.');
        }

        self::$httpStatus = $httpStatus;
    }

    /** Returns the HTTP Status code. */
    public static function getStatus(): string
    {
        return self::$httpStatus;
    }

    /** Returns a request save NONCE für CSP Headers and Implemntations. */
    public static function getNonce(): string
    {
        if (!self::$nonce) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    /** Set a http response header. A existing header with the same name will be overridden. */
    public static function setHeader(string $name, string $value): void
    {
        self::$additionalHeaders[$name] = $value;
    }

    private static function sendAdditionalHeaders(): void
    {
        foreach (self::$additionalHeaders as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /** Set a file to be preload via http link header. */
    public static function preload(string $file, string $type, string $mimeType): void
    {
        self::$preloadFiles[] = [
            'file' => $file,
            'type' => $type,
            'mimeType' => $mimeType,
        ];
    }

    private static function sendPreloadHeaders(): void
    {
        foreach (self::$preloadFiles as $preloadFile) {
            header('Link: <' . $preloadFile['file'] . '>; rel=preload; as=' . $preloadFile['type'] . '; type="' . $preloadFile['mimeType'] . '"; crossorigin; nopush', false);
        }
    }

    /**
     * Redirects to a URL.
     *
     * NOTE: Execution will stop within this method!
     *
     * @param self::HTTP_MOVED_PERMANENTLY|self::HTTP_MOVED_TEMPORARILY|null $httpStatus
     */
    public static function sendRedirect(string $url, ?string $httpStatus = null): never
    {
        if (str_contains($url, "\n")) {
            throw new InvalidArgumentException('Invalid redirect url "' . $url . '", contains newlines');
        }

        if ($httpStatus) {
            self::setStatus($httpStatus);
        }

        self::cleanOutputBuffers();
        self::sendAdditionalHeaders();
        self::sendPreloadHeaders();

        header('HTTP/1.1 ' . self::$httpStatus);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Sends a file to client.
     *
     * @param string $file File path
     * @param string $contentType Content type
     * @param string $contentDisposition Content disposition ("inline" or "attachment")
     * @param string|null $filename Custom Filename
     */
    public static function sendFile(string $file, string $contentType, string $contentDisposition = 'inline', ?string $filename = null): void
    {
        self::cleanOutputBuffers();

        if (!is_file($file)) {
            header('HTTP/1.1 ' . self::HTTP_NOT_FOUND);
            exit;
        }

        // prevent session locking while sending huge files
        session_write_close();

        if (!$filename) {
            $filename = Path::basename($file);
        }

        self::sendContentType($contentType);
        header('Content-Disposition: ' . $contentDisposition . '; filename="' . $filename . '"');

        self::sendLastModified(filemtime($file));

        header('HTTP/1.1 ' . self::$httpStatus);
        if (!self::$sentCacheControl) {
            self::sendCacheControl('max-age=3600, must-revalidate, proxy-revalidate, private');
        }

        // content length schicken, damit der browser einen ladebalken anzeigen kann
        if (!ini_get('zlib.output_compression')) {
            header('Content-Length: ' . filesize($file));
        }

        self::sendAdditionalHeaders();
        self::sendPreloadHeaders();

        header('Accept-Ranges: bytes');
        $rangeHeader = Request::server('HTTP_RANGE', 'string', null);
        if ($rangeHeader) {
            try {
                $filesize = Type::int(filesize($file));
                $unitFactory = new UnitFactory();
                $ranges = $unitFactory->getUnit(trim($rangeHeader), $filesize)->getRanges();
                $handle = fopen($file, 'r');
                if (is_resource($handle)) {
                    foreach ($ranges as $range) {
                        header('HTTP/1.1 ' . self::HTTP_PARTIAL_CONTENT);
                        header('Content-Length: ' . (string) $range->getLength());
                        header('Content-Range: bytes ' . (string) $range->getStart() . '-' . (string) $range->getEnd() . '/' . $filesize);

                        // Don't output more bytes as requested
                        // default chunk size is usually 8192 bytes
                        $chunkSize = $range->getLength() > 8192 ? 8192 : (int) $range->getLength();

                        fseek($handle, (int) $range->getStart());
                        while (ftell($handle) < $range->getEnd()) {
                            // Abort on client disconnect.
                            // With ignore_user_abort(true), the script is not aborted on client disconnect.
                            // To avoid reading the entire stream and dismissing the data afterward, check between the chunks if the client is still there.
                            if (1 === ignore_user_abort() && 1 === connection_aborted()) {
                                break 2;
                            }
                            echo fread($handle, $chunkSize);
                        }
                    }
                    fclose($handle);
                } else {
                    // Send Error if file couldn't be read
                    header('HTTP/1.1 ' . self::HTTP_INTERNAL_ERROR);
                }
            } catch (HttpRangeException) {
                header('HTTP/1.1 ' . self::HTTP_RANGE_NOT_SATISFIABLE);
            }
            return;
        }

        readfile($file);
    }

    /**
     * Sends a resource to the client.
     *
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     * @param string|null $contentDisposition Content disposition ("inline" or "attachment")
     */
    public static function sendResource(string $content, ?string $contentType = null, ?int $lastModified = null, ?string $etag = null, ?string $contentDisposition = null, ?string $filename = null): void
    {
        if ($contentDisposition) {
            header('Content-Disposition: ' . $contentDisposition . '; filename="' . $filename . '"');
        }

        if (!self::$sentCacheControl) {
            self::sendCacheControl();
        }
        self::sendContent($content, $contentType, $lastModified, $etag);
    }

    /**
     * Sends a page to client.
     *
     * The page content can be modified by the Extension Point OUTPUT_FILTER
     */
    public static function sendPage(string $content): void
    {
        // ----- EXTENSION POINT
        $content = Extension::dispatch(new ExtensionPoint('OUTPUT_FILTER', $content));

        $hasShutdownExtension = Extension::hasExtensions('RESPONSE_SHUTDOWN');
        if ($hasShutdownExtension) {
            self::$closeConnection = true;
        }

        self::sendContent($content);

        // ----- EXTENSION POINT - (read only)
        if ($hasShutdownExtension) {
            // unlock session
            session_write_close();

            Extension::dispatch(new ExtensionPoint('RESPONSE_SHUTDOWN', $content, [], true));
        }
    }

    /**
     * Sends content to the client.
     *
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     */
    public static function sendContent(string $content, ?string $contentType = null, ?int $lastModified = null, ?string $etag = null): void
    {
        if (!self::$sentContentType) {
            self::sendContentType($contentType);
        }
        if (!self::$sentCacheControl) {
            self::sendCacheControl();
        }

        $environment = Core::isBackend() ? 'backend' : 'frontend';

        if (self::HTTP_OK == self::$httpStatus) {
            // ----- Last-Modified
            if (!self::$sentLastModified && null !== $lastModified) {
                self::sendLastModified($lastModified);
            }

            // ----- ETAG
            if (!self::$sentEtag
                && (true === Core::getProperty('use_etag') || Core::getProperty('use_etag') === $environment)
            ) {
                self::sendEtag($etag ?: self::md5($content));
            }
        }

        self::cleanOutputBuffers();

        header('HTTP/1.1 ' . self::$httpStatus);

        // content length schicken, damit der browser einen ladebalken anzeigen kann
        header('Content-Length: ' . Str::size($content));

        self::sendAdditionalHeaders();
        self::sendPreloadHeaders();

        $finish = null;
        if (function_exists('fastcgi_finish_request')) {
            $finish = fastcgi_finish_request(...);
        } elseif (function_exists('litespeed_finish_request')) {
            $finish = litespeed_finish_request(...);
        } elseif (self::$closeConnection) {
            header('Connection: close');
        }

        echo $content;

        if ($finish) {
            $finish();
        } elseif (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            flush();
        }
    }

    /**
     * @param mixed $data data to be json encoded and sent
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     */
    public static function sendJson(mixed $data, ?int $lastModified = null, ?string $etag = null): void
    {
        self::sendContent(json_encode($data), 'application/json', $lastModified, $etag);
    }

    /** Cleans all output buffers. */
    public static function cleanOutputBuffers(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    /** Sends the content type header. */
    public static function sendContentType(?string $contentType = null): void
    {
        header('Content-Type: ' . ($contentType ?: 'text/html; charset=utf-8'));
        self::$sentContentType = true;
    }

    /** Sends the cache control header. */
    public static function sendCacheControl(string $cacheControl = 'must-revalidate, proxy-revalidate, private, no-cache, max-age=0'): void
    {
        header('Cache-Control: ' . $cacheControl);
        self::$sentCacheControl = true;
    }

    /**
     * Checks if content has changed by the last modified timestamp.
     *
     * HTTP_IF_MODIFIED_SINCE feature
     *
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     */
    public static function sendLastModified(?int $lastModified = null): void
    {
        if (!$lastModified) {
            $lastModified = time();
        }

        $lastModified = gmdate('D, d M Y H:i:s T', $lastModified);

        // Sende Last-Modification time
        header('Last-Modified: ' . $lastModified);

        // Last-Modified Timestamp gefunden
        // => den Browser anweisen, den Cache zu verwenden
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastModified) {
            self::cleanOutputBuffers();

            header('HTTP/1.1 ' . self::HTTP_NOT_MODIFIED);
            exit;
        }
        self::$sentLastModified = true;
    }

    /**
     * Checks if content has changed by the etag cachekey.
     *
     * HTTP_IF_NONE_MATCH feature
     *
     * @param string $cacheKey HTTP Cachekey to identify the cache
     */
    public static function sendEtag(string $cacheKey): void
    {
        // Laut HTTP Spec muss der Etag in " sein
        $cacheKey = '"' . $cacheKey . '"';

        // Sende CacheKey als ETag
        header('ETag: ' . $cacheKey);

        // CacheKey gefunden
        // => den Browser anweisen, den Cache zu verwenden
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $cacheKey) {
            self::cleanOutputBuffers();

            header('HTTP/1.1 ' . self::HTTP_NOT_MODIFIED);
            exit;
        }
        self::$sentEtag = true;
    }

    // method inspired by https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Cookie.php

    /**
     * @param string $name The name of the cookie
     * @param string|null $value the value of the cookie, a empty value to delete the cookie
     * @param array{expires?: int|string|DateTimeInterface, path?: string, domain?: ?string, secure?: bool, httponly?: bool, samesite?: ?string, raw?: bool} $options Different cookie Options. Supported keys are:
     *                             "expires" int|string|DateTimeInterface The time the cookie expires
     *                             "path" string                          The path on the server in which the cookie will be available on
     *                             "domain" string|null                   The domain that the cookie is available to
     *                             "secure" bool                          Whether the cookie should only be transmitted over a secure HTTPS connection from the client
     *                             "httponly" bool                        Whether the cookie will be made accessible only through the HTTP protocol
     *                             "samesite" string|null                 Whether the cookie will be available for cross-site requests
     *                             "raw" bool                             Whether the cookie value should be sent with no url encoding
     */
    public static function sendCookie(string $name, ?string $value, array $options = []): void
    {
        $expire = $options['expires'] ?? 0;
        $path = $options['path'] ?? '/';
        $domain = $options['domain'] ?? null;
        $secure = $options['secure'] ?? false;
        $httpOnly = $options['httponly'] ?? true;
        $sameSite = $options['samesite'] ?? null;
        $raw = $options['raw'] ?? false;

        // from PHP source code
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }
        if (empty($name)) {
            throw new InvalidArgumentException('The cookie name cannot be empty.');
        }
        // convert expiration time to a Unix timestamp
        if ($expire instanceof DateTimeInterface) {
            $expire = $expire->format('U');
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);
            if (false === $expire) {
                throw new InvalidArgumentException('The cookie expiration time is not valid.');
            }
        }

        $expire = 0 < $expire ? (int) $expire : 0;
        $maxAge = $expire - time();
        $maxAge = 0 >= $maxAge ? 0 : $maxAge;
        $path = empty($path) ? '/' : $path;

        if (null !== $sameSite) {
            $sameSite = strtolower($sameSite);
        }
        if (!in_array($sameSite, ['lax', 'strict', 'none', null], true)) {
            throw new InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        $str = 'Set-Cookie: ' . ($raw ? $name : urlencode($name)) . '=';
        if ('' === (string) $value) {
            $str .= 'deleted; expires=' . gmdate('D, d-M-Y H:i:s T', time() - 31_536_001) . '; Max-Age=0';
        } else {
            $str .= $raw ? $value : rawurlencode($value);
            if (0 !== $expire) {
                $str .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $expire) . '; Max-Age=' . $maxAge;
            }
        }
        if ($path) {
            $str .= '; path=' . $path;
        }
        if ($domain) {
            $str .= '; domain=' . $domain;
        }
        if ($secure) {
            $str .= '; secure';
        }
        if ($httpOnly) {
            $str .= '; httponly';
        }
        if ($sameSite) {
            $str .= '; samesite=' . $sameSite;
        }

        header($str, false);
    }

    /**
     * Clear the given cookie by name.
     *
     * You might pass additional options in case the name is not unique or the cookie is not stored on the current domain.
     *
     * @param string $name The name of the cookie
     * @param array{path?: string, domain?: ?string, secure?: bool, httponly?: bool, samesite?: ?string} $options Different cookie Options. Supported keys are:
     *                        "path" string          The path on the server in which the cookie will be available on
     *                        "domain" string|null   The domain that the cookie is available to
     *                        "secure" bool          Whether the cookie should only be transmitted over a secure HTTPS connection from the client
     *                        "httponly" bool        Whether the cookie will be made accessible only through the HTTP protocol
     *                        "samesite" string|null Whether the cookie will be available for cross-site requests
     */
    public static function clearCookie(string $name, array $options = []): void
    {
        $options['expires'] = 1;
        // clear the cookie by sending it again with an expiration in the past
        self::sendCookie($name, null, $options);
    }

    /**
     * Creates the md5 checksum for the content.
     *
     * Dynamic content surrounded by `<!--DYN-->…<!--/DYN-->` is ignored.
     */
    private static function md5(string $content): string
    {
        return md5(preg_replace('@<!--DYN-->.*<!--/DYN-->@U', '', $content));
    }

    public static function enforceHttps(): void
    {
        if (!Request::isHttps()) {
            self::setStatus(self::HTTP_MOVED_PERMANENTLY);
            self::sendRedirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }
    }
}
