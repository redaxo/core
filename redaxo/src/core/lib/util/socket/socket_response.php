<?php

/**
 * Class for rex_socket responses.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_socket_response
{
    /** @var resource */
    private $stream;
    private bool $chunked;
    /** @var int */
    private $statusCode;
    /** @var string */
    private $statusMessage;
    private string $header = '';
    /** @var array */
    private $headers = [];
    /** @var string|null */
    private $body;
    /** @var bool */
    private $decompressContent = false;
    /** @var bool */
    private $streamFiltersInitialized = false;

    /**
     * @param resource $stream Socket stream
     *
     * @throws InvalidArgumentException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Expecting $stream to be a resource, but %s given!', gettype($stream)));
        }

        $this->stream = $stream;

        while (!feof($this->stream) && !str_contains($this->header, "\r\n\r\n")) {
            $this->header .= fgets($this->stream);
        }
        $this->header = rtrim($this->header);
        if (preg_match('@^HTTP/1\.\d ([0-9]+) (\V+)@', $this->header, $matches)) {
            $this->statusCode = (int) $matches[1];
            $this->statusMessage = $matches[2];
        }
        $this->chunked = false !== stripos($this->header, 'transfer-encoding: chunked');
    }

    /** @return $this */
    public function decompressContent(bool $decodeContent): self
    {
        $this->decompressContent = $decodeContent;
        return $this;
    }

    /**
     * Returns the HTTP status code, e.g. 200.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Returns the HTTP status message, e.g. "OK".
     *
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    /**
     * Returns wether the status is "200 OK".
     *
     * @return bool
     */
    public function isOk()
    {
        return 200 == $this->statusCode;
    }

    /**
     * Returns wether the status class is "Informational".
     *
     * @return bool
     */
    public function isInformational()
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Returns wether the status class is "Success".
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Returns wether the status class is "Redirection".
     *
     * @return bool
     */
    public function isRedirection()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Returns wether the status class is "Client Error".
     *
     * @return bool
     */
    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Returns wether the status class is "Server Error".
     *
     * @return bool
     */
    public function isServerError()
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Returns wether the status is invalid.
     *
     * @return bool
     */
    public function isInvalid()
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * Returns the header for the given key, or the entire header if no key is given.
     *
     * @param string $key Header key
     * @param string $default Default value (is returned if the header is not set)
     *
     * @return string|null
     */
    public function getHeader($key = null, $default = null)
    {
        if (null === $key) {
            return $this->header;
        }
        $key = strtolower($key);
        if (isset($this->headers[$key])) {
            return $this->headers[$key];
        }
        if (preg_match('@^' . preg_quote($key, '@') . ': (\V*)@im', $this->header, $matches)) {
            return $this->headers[$key] = $matches[1];
        }
        return $this->headers[$key] = $default;
    }

    /**
     * Returns an array with all applied content encodings.
     *
     * @return list<string>
     */
    public function getContentEncodings(): array
    {
        $contenEncodingHeader = $this->getHeader('Content-Encoding');

        if (null === $contenEncodingHeader) {
            return [];
        }

        return array_map(static function ($encoding) {
            return trim(strtolower($encoding));
        }, explode(',', $contenEncodingHeader));
    }

    /**
     * Returns up to `$length` bytes from the body, or `false` if the end is reached.
     *
     * @param int $length Max number of bytes
     *
     * @return false|string
     */
    public function getBufferedBody($length = 1024)
    {
        if (feof($this->stream)) {
            return false;
        }

        if (!$this->streamFiltersInitialized) {
            if ($this->chunked) {
                if (!is_resource(stream_filter_append($this->stream, 'dechunk', STREAM_FILTER_READ))) {
                    throw new rex_exception('Could not add dechunk filter to socket stream');
                }
            }

            if ($this->decompressContent && $this->isGzipOrDeflateEncoded()) {
                $this->addZlibStreamFilter($this->stream, STREAM_FILTER_READ);
            }

            $this->streamFiltersInitialized = true;
        }

        return fread($this->stream, $length);
    }

    /**
     * Returns the entire body.
     *
     * @return string
     */
    public function getBody()
    {
        if (null === $this->body) {
            $this->body = '';

            while (false !== ($buf = $this->getBufferedBody())) {
                $this->body .= $buf;
            }
        }
        return $this->body;
    }

    protected function isGzipOrDeflateEncoded(): bool
    {
        $contentEncodings = $this->getContentEncodings();
        return in_array('gzip', $contentEncodings) || in_array('deflate', $contentEncodings);
    }

    /**
     * @param resource $stream
     * @throws rex_exception
     * @return resource
     */
    private function addZlibStreamFilter($stream, int $mode)
    {
        if (!is_resource($stream)) {
            throw new rex_exception('The stream has to be a resource.');
        }

        if (!in_array('zlib.*', stream_get_filters())) {
            throw new rex_exception('The zlib filter for streams is missing.');
        }

        if (!in_array($mode, [STREAM_FILTER_READ, STREAM_FILTER_WRITE])) {
            throw new rex_exception('Invalid stream filter mode.');
        }

        $appendedZlibStreamFilter = stream_filter_append(
            $stream,
            'zlib.inflate',
            $mode,
            ['window' => 47],    // To detect gzip and zlib header
        );

        if (!is_resource($appendedZlibStreamFilter)) {
            throw new rex_exception('Could not add stream filter for gzip support.');
        }

        return $appendedZlibStreamFilter;
    }

    /**
     * Writes the body to the given resource.
     *
     * @param string|resource $resource File path or file pointer
     *
     * @return bool `true` on success, `false` on failure
     */
    public function writeBodyTo($resource)
    {
        $close = false;
        if (is_string($resource) && rex_dir::create(dirname($resource))) {
            $resource = fopen($resource, 'w');
            $close = true;
        }
        if (!is_resource($resource)) {
            return false;
        }

        $success = true;
        while ($success && false !== ($buf = $this->getBufferedBody())) {
            $success = (bool) fwrite($resource, $buf);
        }
        if ($close) {
            fclose($resource);
        }
        return $success;
    }
}
