<?php

use Ramsey\Http\Range\Exception\HttpRangeException;
use Ramsey\Http\Range\UnitFactory;

/**
 * HTTP1.1 Client Cache Features.
 *
 * @package redaxo\core
 */
class rex_response
{
    public const HTTP_OK = '200 OK';
    public const HTTP_PARTIAL_CONTENT = '206 Partial Content';
    public const HTTP_MOVED_PERMANENTLY = '301 Moved Permanently';
    public const HTTP_NOT_MODIFIED = '304 Not Modified';
    public const HTTP_MOVED_TEMPORARILY = '307 Temporary Redirect';
    public const HTTP_BAD_REQUEST = '400 Bad Request';
    public const HTTP_NOT_FOUND = '404 Not Found';
    public const HTTP_FORBIDDEN = '403 Forbidden';
    public const HTTP_UNAUTHORIZED = '401 Unauthorized';
    public const HTTP_RANGE_NOT_SATISFIABLE = '416 Range Not Satisfiable';
    public const HTTP_INTERNAL_ERROR = '500 Internal Server Error';
    public const HTTP_SERVICE_UNAVAILABLE = '503 Service Unavailable';

    private static string $httpStatus = self::HTTP_OK;
    private static bool $sentLastModified = false;
    private static bool $sentEtag = false;
    private static bool $sentContentType = false;
    private static bool $sentCacheControl = false;
    private static bool $closeConnection = false;
    private static array $additionalHeaders = [];
    private static array $preloadFiles = [];
    private static string $nonce = '';

    /**
     * Sets the HTTP Status code.
     *
     * @param string $httpStatus
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public static function setStatus($httpStatus)
    {
        if (str_contains($httpStatus, "\n")) {
            throw new InvalidArgumentException('Illegal http-status "' . $httpStatus . '", contains newlines');
        }

        self::$httpStatus = $httpStatus;
    }

    /**
     * Returns the HTTP Status code.
     *
     * @return string
     */
    public static function getStatus()
    {
        return self::$httpStatus;
    }

    /**
     * Returns a request save NONCE für CSP Headers and Implemntations.
     */
    public static function getNonce(): string
    {
        if (!self::$nonce) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Set a http response header. A existing header with the same name will be overridden.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public static function setHeader($name, $value)
    {
        self::$additionalHeaders[$name] = $value;
    }

    /**
     * @return void
     */
    private static function sendAdditionalHeaders()
    {
        foreach (self::$additionalHeaders as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Set a file to be preload via http link header.
     *
     * @param string $file
     * @param string $type
     * @param string $mimeType
     * @return void
     */
    public static function preload($file, $type, $mimeType)
    {
        self::$preloadFiles[] = [
            'file' => $file,
            'type' => $type,
            'mimeType' => $mimeType,
        ];
    }

    /**
     * @return void
     */
    private static function sendPreloadHeaders()
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
     * @param string $url URL
     * @param self::HTTP_MOVED_PERMANENTLY|self::HTTP_MOVED_TEMPORARILY|null $httpStatus
     *
     * @throws InvalidArgumentException
     *
     * @return never
     */
    public static function sendRedirect($url, $httpStatus = null)
    {
        if (str_contains($url, "\n")) {
            throw new InvalidArgumentException('Illegal redirect url "' . $url . '", contains newlines');
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
     * @return void|never
     */
    public static function sendFile($file, $contentType, $contentDisposition = 'inline', $filename = null)
    {
        self::cleanOutputBuffers();

        if (!is_file($file)) {
            header('HTTP/1.1 ' . self::HTTP_NOT_FOUND);
            exit;
        }

        // prevent session locking while sending huge files
        session_write_close();

        if (!$filename) {
            $filename = rex_path::basename($file);
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
        $rangeHeader = rex_request::server('HTTP_RANGE', 'string', null);
        if ($rangeHeader) {
            try {
                $filesize = rex_type::int(filesize($file));
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
     * @param string $content Content
     * @param string|null $contentType Content type
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     * @param string|null $contentDisposition Content disposition ("inline" or "attachment")
     * @param string|null $filename Filename
     * @return void
     */
    public static function sendResource($content, $contentType = null, $lastModified = null, $etag = null, $contentDisposition = null, $filename = null)
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
     *
     * @param string $content Content of page
     * @param int $lastModified HTTP Last-Modified Timestamp
     * @return void
     */
    public static function sendPage($content, $lastModified = null)
    {
        // ----- EXTENSION POINT
        $content = rex_extension::registerPoint(new rex_extension_point('OUTPUT_FILTER', $content));

        $hasShutdownExtension = rex_extension::isRegistered('RESPONSE_SHUTDOWN');
        if ($hasShutdownExtension) {
            self::$closeConnection = true;
        }

        self::sendContent($content, null, $lastModified);

        // ----- EXTENSION POINT - (read only)
        if ($hasShutdownExtension) {
            // unlock session
            session_write_close();

            rex_extension::registerPoint(new rex_extension_point('RESPONSE_SHUTDOWN', $content, [], true));
        }
    }

    /**
     * Sends content to the client.
     *
     * @param string $content Content
     * @param string|null $contentType Content type
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     * @return void
     */
    public static function sendContent($content, $contentType = null, $lastModified = null, $etag = null)
    {
        if (!self::$sentContentType) {
            self::sendContentType($contentType);
        }
        if (!self::$sentCacheControl) {
            self::sendCacheControl();
        }

        $environment = rex::isBackend() ? 'backend' : 'frontend';

        if (self::HTTP_OK == self::$httpStatus) {
            // ----- Last-Modified
            if (!self::$sentLastModified
                && (true === rex::getProperty('use_last_modified') || rex::getProperty('use_last_modified') === $environment)
            ) {
                self::sendLastModified($lastModified);
            }

            // ----- ETAG
            if (!self::$sentEtag
                && (true === rex::getProperty('use_etag') || rex::getProperty('use_etag') === $environment)
            ) {
                self::sendEtag($etag ?: self::md5($content));
            }
        }

        // ----- GZIP
        if (true === rex::getProperty('use_gzip') || rex::getProperty('use_gzip') === $environment) {
            $content = self::sendGzip($content);
        }

        self::cleanOutputBuffers();

        header('HTTP/1.1 ' . self::$httpStatus);

        // content length schicken, damit der browser einen ladebalken anzeigen kann
        header('Content-Length: ' . rex_string::size($content));

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
        } elseif (!in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            flush();
        }
    }

    /**
     * @param mixed $data data to be json encoded and sent
     * @param int|null $lastModified HTTP Last-Modified Timestamp
     * @param string|null $etag HTTP Cachekey to identify the cache
     */
    public static function sendJson($data, ?int $lastModified = null, ?string $etag = null): void
    {
        self::sendContent(json_encode($data), 'application/json', $lastModified, $etag);
    }

    /**
     * Cleans all output buffers.
     * @return void
     */
    public static function cleanOutputBuffers()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * Sends the content type header.
     *
     * @param string $contentType
     * @return void
     */
    public static function sendContentType($contentType = null)
    {
        header('Content-Type: ' . ($contentType ?: 'text/html; charset=utf-8'));
        self::$sentContentType = true;
    }

    /**
     * Sends the cache control header.
     * @param string $cacheControl
     * @return void
     */
    public static function sendCacheControl($cacheControl = 'must-revalidate, proxy-revalidate, private, no-cache, max-age=0')
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
     * @return void|never
     */
    public static function sendLastModified($lastModified = null)
    {
        if (!$lastModified) {
            $lastModified = time();
        }

        $lastModified = gmdate('D, d M Y H:i:s T', (int) $lastModified);

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
     * @return void|never
     */
    public static function sendEtag($cacheKey)
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

    /**
     * Encodes the content with GZIP/X-GZIP if the browser supports one of them.
     *
     * HTTP_ACCEPT_ENCODING feature
     *
     * @param string $content Content
     *
     * @return string
     */
    protected static function sendGzip($content)
    {
        $enc = '';
        $encodings = [];
        $supportsGzip = false;

        // Check if it supports gzip
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $encodings = explode(',', strtolower(preg_replace('/\s+/', '', $_SERVER['HTTP_ACCEPT_ENCODING'])));
        }

        if ((in_array('gzip', $encodings) || in_array('x-gzip', $encodings) || isset($_SERVER['---------------']))
            && function_exists('ob_gzhandler')
            && !ini_get('zlib.output_compression')
        ) {
            $enc = in_array('x-gzip', $encodings) ? 'x-gzip' : 'gzip';
            $supportsGzip = true;
        }

        if ($supportsGzip) {
            header('Content-Encoding: ' . $enc);
            $content = gzencode($content, 9, FORCE_GZIP);
        }

        return $content;
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
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public static function sendCookie($name, $value, array $options = [])
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
     *
     * @throws InvalidArgumentException
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
     *
     * @param string $content
     *
     * @return string
     */
    private static function md5($content)
    {
        return md5(preg_replace('@<!--DYN-->.*<!--/DYN-->@U', '', $content));
    }

    /**
     * @return void
     */
    public static function enforceHttps()
    {
        if (!rex_request::isHttps()) {
            self::setStatus(self::HTTP_MOVED_PERMANENTLY);
            self::sendRedirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        }
    }
}
