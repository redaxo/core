<?php

namespace Redaxo\Core\HttpClient;

use Closure;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\HttpClient\Exception\HttpClientException;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use SensitiveParameter;

use function in_array;
use function ini_get;
use function is_array;
use function is_string;
use function sprintf;

use const E_WARNING;
use const STREAM_CLIENT_CONNECT;

/**
 * Class for HttpClient requests.
 *
 * Example:
 * <code>
 *  try {
 *      //Open socket connection. (Host, Port, SSL)
 *      $socket = Request::factory('www.example.com','443', true);
 *      //set path to Request
 *      $socket->setPath('/url/to/my/resource?param=1');
 *      //set PHP Context-Option
 *      $socket->setOptions([
 *          'ssl' => [
 *              'verify_peer' => false,
 *              'verify_peer_name' => false
 *          ]
 *      ]);
 *      //make request and get Response-Object back
 *      $response = $socket->doGet();
 *      //check if status code is 200
 *      if($response->isOk()) {
 *          //get file body
 *          $body = $response->getBody();
 *      }
 *  } catch(HttpClientException $e) {
 *      //error message: $e->getMessage()
 *  }
 * </code>
 */
class Request
{
    protected string $host;
    protected int $port;
    protected bool $ssl;
    protected string $path = '/';
    protected int $timeout = 15;
    protected int|false $followRedirects = false;
    /** @var array<string, string> */
    protected array $headers = [];
    /** @var resource */
    protected $stream;
    /** @var array<mixed> */
    protected array $options = [];
    protected bool $acceptCompression = false;

    /**
     * @param string $host Host name
     * @param int $port Port number
     * @param bool $ssl SSL flag
     */
    protected function __construct(string $host, int $port = 443, bool $ssl = true)
    {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;

        $this->addHeader('Host', $this->host);
        $this->addHeader('User-Agent', 'REDAXO/' . Core::getVersion());
        $this->addHeader('Connection', 'Close');
    }

    /** @see Request::factoryUrl() */
    public static function factory(string $host, int $port = 443, bool $ssl = true): static
    {
        if (self::class === static::class && ($proxy = Core::getProperty('http_client_proxy'))) {
            $request = ProxyRequest::factoryUrl($proxy);
            $request->setDestination($host, $port, $ssl);

            /** @psalm-suppress LessSpecificReturnStatement */
            return $request; // @phpstan-ignore-line
        }

        return new static($host, $port, $ssl);
    }

    /**
     * Creates a socket by a full URL.
     *
     * @see Request::factory()
     */
    public static function factoryUrl(string $url): static
    {
        $parts = self::parseUrl($url);

        return static::factory($parts['host'], $parts['port'], $parts['ssl'])->setPath($parts['path']);
    }

    public function acceptCompression(): static
    {
        $this->acceptCompression = true;
        $this->addHeader('Accept-Encoding', 'gzip, deflate');
        return $this;
    }

    /** @return $this */
    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Sets the socket context options.
     *
     * Available options can be found on https://www.php.net/manual/en/context.php
     *
     * @param array<mixed> $options
     * @return $this
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Adds a header to the current request.
     *
     * @return $this
     */
    public function addHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Adds the basic authorization header to the current request.
     *
     * @return $this
     */
    public function addBasicAuthorization(#[SensitiveParameter] string $user, #[SensitiveParameter] string $password): static
    {
        $this->addHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $password));

        return $this;
    }

    /**
     * Sets the timeout for the connection.
     *
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Sets number of redirects that should be followed automatically.
     *
     * The method only affects GET requests.
     *
     * @param false|int $redirects Number of max redirects
     *
     * @return $this
     */
    public function followRedirects(int|false $redirects): static
    {
        if (false !== $redirects && $redirects < 0) {
            throw new InvalidArgumentException(sprintf('$redirects must be `false` or an int >= 0, given "%s".', $redirects));
        }

        $this->followRedirects = $redirects;

        return $this;
    }

    /**
     * Makes a GET request.
     *
     * @throws HttpClientException
     */
    public function doGet(): Response
    {
        return $this->doRequest('GET');
    }

    /**
     * Makes a POST request.
     *
     * @param string|array<string, string>|Closure(resource): void $data Body data as string or array (POST parameters) or a Closure for writing the body
     * @param array<string, array{path: string, type: string}> $files Files array, e.g. `array('myfile' => array('path' => $path, 'type' => 'image/png'))`
     *
     * @throws HttpClientException
     */
    public function doPost(string|array|Closure $data = '', array $files = []): Response
    {
        if (is_array($data) && !empty($files)) {
            $data = /** @param resource $stream */ static function ($stream) use ($data, $files) {
                $boundary = '----------6n2Yd9bk2liD6piRHb5xF6';
                $eol = "\r\n";
                fwrite($stream, 'Content-Type: multipart/form-data; boundary=' . $boundary . $eol);
                $dataFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"' . $eol . $eol;
                $fileFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"; filename="%s"' . $eol . 'Content-Type: %s' . $eol . $eol;
                $end = '--' . $boundary . '--' . $eol;
                $length = 0;
                $temp = explode('&', Str::buildQuery($data));
                $data = [];
                $partLength = Str::size(sprintf($dataFormat, '') . $eol);
                foreach ($temp as $t) {
                    $t = explode('=', $t, 2);
                    $key = urldecode($t[0]);
                    $value = urldecode(Type::notNull($t[1] ?? null));
                    $data[$key] = $value;
                    $length += $partLength + Str::size($key) + Str::size($value);
                }
                $partLength = Str::size(sprintf($fileFormat, '', '', '') . $eol);
                foreach ($files as $key => $file) {
                    $length += $partLength + Str::size($key) + Str::size(Path::basename($file['path'])) + Str::size($file['type']) + filesize($file['path']);
                }
                $length += Str::size($end);
                fwrite($stream, 'Content-Length: ' . $length . $eol . $eol);
                foreach ($data as $key => $value) {
                    fwrite($stream, sprintf($dataFormat, $key) . $value . $eol);
                }
                foreach ($files as $key => $file) {
                    fwrite($stream, sprintf($fileFormat, $key, Path::basename($file['path']), $file['type']));
                    $file = fopen($file['path'], 'r');
                    while (!feof($file)) {
                        fwrite($stream, fread($file, 1024));
                    }
                    fclose($file);
                    fwrite($stream, $eol);
                }
                fwrite($stream, $end);
            };
        } elseif (!$data instanceof Closure && is_array($data)) {
            $data = Str::buildQuery($data);
            $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $this->doRequest('POST', $data);
    }

    /**
     * Makes a DELETE request.
     *
     * @throws HttpClientException
     */
    public function doDelete(): Response
    {
        return $this->doRequest('DELETE');
    }

    /**
     * Makes a request.
     *
     * @param string $method HTTP method, e.g. "GET"
     * @param string|Closure(resource): void $data Body data as string or a Closure for writing the body
     */
    public function doRequest(string $method, string|Closure $data = ''): Response
    {
        return Timer::measure('Socket request: ' . $this->host . $this->path, function () use ($method, $data) {
            if (!$this->ssl) {
                Logger::logError(E_WARNING, 'You should not use non-secure socket connections while connecting to "' . $this->host . '"!', __FILE__, __LINE__);
            }

            $this->openConnection();
            $response = $this->writeRequest($method, $this->path, $this->headers, $data);

            if ('GET' !== $method || !$this->followRedirects || !$response->isRedirection()) {
                return $response;
            }

            $location = $response->getHeader('location');

            if (!$location) {
                return $response;
            }

            if (!str_contains($location, '//')) {
                $socket = self::factory($this->host, $this->port, $this->ssl)->setPath($location);
            } else {
                $socket = self::factoryUrl($location);

                if ($this->ssl && !$socket->ssl) {
                    return $response;
                }
            }

            $socket->setTimeout($this->timeout);
            $socket->followRedirects($this->followRedirects - 1);

            foreach ($this->headers as $key => $value) {
                if ('Host' !== $key) {
                    $socket->addHeader($key, $value);
                }
            }

            return $socket->doGet();
        });
    }

    /**
     * Opens the socket connection.
     *
     * @throws HttpClientException
     */
    protected function openConnection(): void
    {
        $host = ($this->ssl ? 'ssl://' : '') . $this->host;

        $errno = 0;
        $errstr = '';
        $prevError = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$prevError): false {
            if (null === $prevError) {
                $prevError = $errstr;
            }

            return false;
        });

        try {
            $context = stream_context_create($this->options);
            $this->stream = stream_socket_client($host . ':' . $this->port, $errno, $errstr, (float) ini_get('default_socket_timeout'), STREAM_CLIENT_CONNECT, $context);
        } finally {
            restore_error_handler();
        }

        if ($this->stream) {
            stream_set_timeout($this->stream, $this->timeout);

            return;
        }

        if ($errstr) {
            throw new HttpClientException($errstr . ' (' . $errno . ')');
        }

        if ($prevError) {
            throw new HttpClientException($prevError);
        }

        throw new HttpClientException('Unknown error.');
    }

    /**
     * Writes a request to the opened connection.
     *
     * @param string $method HTTP method, e.g. "GET"
     * @param string $path Path
     * @param array<string, string> $headers Headers
     * @param string|Closure(resource): void $data Body data as string or a Closure for writing the body
     *
     * @throws HttpClientException
     */
    protected function writeRequest(string $method, string $path, array $headers = [], string|Closure $data = ''): Response
    {
        $eol = "\r\n";
        $headerStrings = [];
        $headerStrings[] = strtoupper($method) . ' ' . $path . ' HTTP/1.1';
        foreach ($headers as $key => $value) {
            $headerStrings[] = $key . ': ' . $value;
        }
        foreach ($headerStrings as $header) {
            fwrite($this->stream, str_replace(["\r", "\n"], '', $header) . $eol);
        }
        if (is_string($data)) {
            fwrite($this->stream, 'Content-Length: ' . Str::size($data) . $eol);
            fwrite($this->stream, $eol . $data);
        } else {
            $data($this->stream);
        }

        $meta = stream_get_meta_data($this->stream);
        if (isset($meta['timed_out']) && $meta['timed_out']) {
            throw new HttpClientException('Timeout.');
        }

        return new Response($this->stream)->decompressContent($this->acceptCompression);
    }

    /**
     * Parses a full URL and returns an array with the keys "host", "port", "ssl" and "path".
     *
     * @param string $url Full URL
     *
     * @return array{host: string, port: int, ssl: bool, path: string} URL parts
     */
    protected static function parseUrl(string $url): array
    {
        $parts = parse_url($url);
        if (false !== $parts && !isset($parts['host']) && !str_starts_with($url, 'http')) {
            $parts = parse_url('https://' . $url);
        }
        if (false === $parts || !isset($parts['host'])) {
            throw new InvalidArgumentException('It isn\'t possible to parse the URL "' . $url . '"!');
        }

        $port = 80;
        $ssl = false;
        if (isset($parts['scheme'])) {
            $supportedProtocols = ['http', 'https'];
            if (!in_array($parts['scheme'], $supportedProtocols)) {
                throw new InvalidArgumentException('Unsupported protocol "' . $parts['scheme'] . '". Supported protocols are ' . implode(', ', $supportedProtocols) . '.');
            }
            if ('https' == $parts['scheme']) {
                $ssl = true;
                $port = 443;
            }
        }
        $port = $parts['port'] ?? $port;

        $path = ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return [
            'host' => $parts['host'],
            'port' => $port,
            'ssl' => $ssl,
            'path' => $path,
        ];
    }
}
