<?php

/**
 * Class for sockets.
 *
 * Example:
 * <code>
 *  try {
 *      //Open socket connection. (Host, Port, SSL)
 *      $socket = rex_socket::factory('www.example.com','443', true);
 *      //set path to rex_socket
 *      $socket->setPath('/url/to/my/resource?param=1');
 *      //set PHP Context-Option
 *      $socket->setOptions([
 *          'ssl' => [
 *              'verify_peer' => false,
 *              'verify_peer_name' => false
 *          ]
 *      ]);
 *      //make request and get rex_socket_request-Objekt back
 *      $response = $socket->doGet();
 *      //check if status code is 200
 *      if($response->isOk()) {
 *          //get file body
 *          $body = $response->getBody();
 *      }
 *  } catch(rex_socket_exception $e) {
 *      //error message: $e->getMessage()
 *  }
 * </code>
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_socket
{
    /** @var string */
    protected $host;
    /** @var int */
    protected $port;
    /** @var bool */
    protected $ssl;
    /** @var string */
    protected $path = '/';
    /** @var int */
    protected $timeout = 15;
    /** @var false|int */
    protected $followRedirects = false;
    /** @var array<string, string> */
    protected $headers = [];
    /** @var resource */
    protected $stream;
    /** @var array<array-key, mixed> */
    protected $options = [];
    /** @var bool */
    protected $acceptCompression = false;

    /**
     * @param string $host Host name
     * @param int $port Port number
     * @param bool $ssl SSL flag
     */
    protected function __construct($host, $port = 80, $ssl = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;

        $this->addHeader('Host', $this->host);
        $this->addHeader('User-Agent', 'REDAXO/' . rex::getVersion());
        $this->addHeader('Connection', 'Close');
    }

    /**
     * Factory method.
     *
     * @param string $host Host name
     * @param int $port Port number
     * @param bool $ssl SSL flag
     *
     * @return static Socket instance
     *
     * @see rex_socket::factoryUrl()
     */
    public static function factory($host, $port = 80, $ssl = false)
    {
        if (self::class === static::class && ($proxy = rex::getProperty('socket_proxy'))) {
            return rex_socket_proxy::factoryUrl($proxy)->setDestination($host, $port, $ssl);
        }

        return new static($host, $port, $ssl);
    }

    /**
     * Creates a socket by a full URL.
     *
     * @param string $url URL
     *
     * @throws rex_socket_exception
     *
     * @return static Socket instance
     *
     * @see rex_socket::factory()
     */
    public static function factoryUrl($url)
    {
        $parts = self::parseUrl($url);

        return static::factory($parts['host'], $parts['port'], $parts['ssl'])->setPath($parts['path']);
    }

    /** @return $this */
    public function acceptCompression(): self
    {
        $this->acceptCompression = true;
        $this->addHeader('Accept-Encoding', 'gzip, deflate');
        return $this;
    }

    /**
     * Sets the path.
     *
     * @param string $path
     *
     * @return $this Current socket
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Sets the socket context options.
     *
     * Available options can be found on https://www.php.net/manual/en/context.php
     *
     * @return $this Current socket
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Adds a header to the current request.
     *
     * @param string $key
     * @param string $value
     *
     * @return $this Current socket
     */
    public function addHeader($key, $value)
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Adds the basic authorization header to the current request.
     *
     * @param string $user
     * @param string $password
     *
     * @return $this Current socket
     */
    public function addBasicAuthorization(#[SensitiveParameter] $user, #[SensitiveParameter] $password)
    {
        $this->addHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $password));

        return $this;
    }

    /**
     * Sets the timeout for the connection.
     *
     * @param int $timeout Timeout
     *
     * @return $this Current socket
     */
    public function setTimeout($timeout)
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
     * @return $this Current socket
     */
    public function followRedirects($redirects)
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
     * @throws rex_socket_exception
     *
     * @return rex_socket_response Response
     */
    public function doGet()
    {
        return $this->doRequest('GET');
    }

    /**
     * Makes a POST request.
     *
     * @param string|array<string, string>|callable(resource): void $data Body data as string or array (POST parameters) or a callback for writing the body
     * @param array<string, array{path: string, type: string}> $files Files array, e.g. `array('myfile' => array('path' => $path, 'type' => 'image/png'))`
     *
     * @throws rex_socket_exception
     *
     * @return rex_socket_response Response
     */
    public function doPost($data = '', array $files = [])
    {
        if (is_array($data) && !empty($files)) {
            $data = static function ($stream) use ($data, $files) {
                $boundary = '----------6n2Yd9bk2liD6piRHb5xF6';
                $eol = "\r\n";
                fwrite($stream, 'Content-Type: multipart/form-data; boundary=' . $boundary . $eol);
                $dataFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"' . $eol . $eol;
                $fileFormat = '--' . $boundary . $eol . 'Content-Disposition: form-data; name="%s"; filename="%s"' . $eol . 'Content-Type: %s' . $eol . $eol;
                $end = '--' . $boundary . '--' . $eol;
                $length = 0;
                $temp = explode('&', rex_string::buildQuery($data));
                $data = [];
                $partLength = rex_string::size(sprintf($dataFormat, '') . $eol);
                foreach ($temp as $t) {
                    [$key, $value] = array_map('urldecode', explode('=', $t, 2));
                    $data[$key] = $value;
                    $length += $partLength + rex_string::size($key) + rex_string::size($value);
                }
                $partLength = rex_string::size(sprintf($fileFormat, '', '', '') . $eol);
                foreach ($files as $key => $file) {
                    $length += $partLength + rex_string::size($key) + rex_string::size(rex_path::basename($file['path'])) + rex_string::size($file['type']) + filesize($file['path']);
                }
                $length += rex_string::size($end);
                fwrite($stream, 'Content-Length: ' . $length . $eol . $eol);
                foreach ($data as $key => $value) {
                    fwrite($stream, sprintf($dataFormat, $key) . $value . $eol);
                }
                foreach ($files as $key => $file) {
                    fwrite($stream, sprintf($fileFormat, $key, rex_path::basename($file['path']), $file['type']));
                    $file = fopen($file['path'], 'r');
                    while (!feof($file)) {
                        fwrite($stream, fread($file, 1024));
                    }
                    fclose($file);
                    fwrite($stream, $eol);
                }
                fwrite($stream, $end);
            };
        } elseif (!is_callable($data)) {
            if (is_array($data)) {
                $data = rex_string::buildQuery($data);
                $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
        }
        return $this->doRequest('POST', $data);
    }

    /**
     * Makes a DELETE request.
     *
     * @throws rex_socket_exception
     *
     * @return rex_socket_response Response
     */
    public function doDelete()
    {
        return $this->doRequest('DELETE');
    }

    /**
     * Makes a request.
     *
     * @param string $method HTTP method, e.g. "GET"
     * @param string|callable(resource): void $data Body data as string or a callback for writing the body
     *
     * @throws InvalidArgumentException
     *
     * @return rex_socket_response Response
     */
    public function doRequest($method, $data = '')
    {
        return rex_timer::measure('Socket request: ' . $this->host . $this->path, function () use ($method, $data) {
            if (!is_string($data) && !is_callable($data)) {
                throw new InvalidArgumentException(sprintf('Expecting $data to be a string or a callable, but %s given!', gettype($data)));
            }

            if (!$this->ssl) {
                rex_logger::logError(E_WARNING, 'You should not use non-secure socket connections while connecting to "' . $this->host . '"!', __FILE__, __LINE__);
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
     * @throws rex_socket_exception
     * @return void
     */
    protected function openConnection()
    {
        $host = ($this->ssl ? 'ssl://' : '') . $this->host;

        $errno = 0;
        $errstr = '';
        $prevError = null;
        set_error_handler(static function ($errno, $errstr) use (&$prevError) {
            if (null === $prevError) {
                $prevError = $errstr;
            }
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
            throw new rex_socket_exception($errstr . ' (' . $errno . ')');
        }

        if ($prevError) {
            throw new rex_socket_exception($prevError);
        }

        throw new rex_socket_exception('Unknown error.');
    }

    /**
     * Writes a request to the opened connection.
     *
     * @param string $method HTTP method, e.g. "GET"
     * @param string $path Path
     * @param array<string, string> $headers Headers
     * @param string|callable(resource): void $data Body data as string or a callback for writing the body
     *
     * @throws rex_socket_exception
     *
     * @return rex_socket_response Response
     */
    protected function writeRequest($method, $path, array $headers = [], $data = '')
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
        if (!is_callable($data)) {
            fwrite($this->stream, 'Content-Length: ' . rex_string::size($data) . $eol);
            fwrite($this->stream, $eol . $data);
        } else {
            call_user_func($data, $this->stream);
        }

        $meta = stream_get_meta_data($this->stream);
        if (isset($meta['timed_out']) && $meta['timed_out']) {
            throw new rex_socket_exception('Timeout!');
        }

        return (new rex_socket_response($this->stream))->decompressContent($this->acceptCompression);
    }

    /**
     * Parses a full URL and returns an array with the keys "host", "port", "ssl" and "path".
     *
     * @param string $url Full URL
     *
     * @throws rex_socket_exception
     *
     * @return array{host: string, port: int, ssl: bool, path: string} URL parts
     */
    protected static function parseUrl($url)
    {
        $parts = parse_url($url);
        if (false !== $parts && !isset($parts['host']) && !str_starts_with($url, 'http')) {
            $parts = parse_url('http://' . $url);
        }
        if (false === $parts || !isset($parts['host'])) {
            throw new rex_socket_exception('It isn\'t possible to parse the URL "' . $url . '"!');
        }

        $port = 80;
        $ssl = false;
        if (isset($parts['scheme'])) {
            $supportedProtocols = ['http', 'https'];
            if (!in_array($parts['scheme'], $supportedProtocols)) {
                throw new rex_socket_exception('Unsupported protocol "' . $parts['scheme'] . '". Supported protocols are ' . implode(', ', $supportedProtocols) . '.');
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

/**
 * Socket exception.
 *
 * @see rex_socket
 *
 * @package redaxo\core
 */
class rex_socket_exception extends rex_exception {}
