<?php

namespace Redaxo\Core\HttpClient;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\HttpClient\Exception\HttpClientException;

use function dirname;
use function gettype;
use function in_array;
use function is_resource;
use function is_string;
use function sprintf;

use const STREAM_FILTER_READ;
use const STREAM_FILTER_WRITE;

/**
 * Class for HttpClient responses.
 */
final class Response
{
    /**
     * @var resource
     * @readonly
     */
    private $stream;
    private readonly bool $chunked;
    private readonly int $statusCode;
    private readonly string $statusMessage;
    private readonly string $header;
    /** @var array<string, string> */
    private array $headers = [];
    private ?string $body = null;
    private bool $decompressContent = false;
    private bool $streamFiltersInitialized = false;

    /**
     * @param resource $stream Socket stream
     * @throws HttpClientException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Expecting $stream to be a resource, but %s given!', gettype($stream)));
        }

        $this->stream = $stream;

        $header = '';
        while (!feof($this->stream) && !str_contains($header, "\r\n\r\n")) {
            $header .= fgets($this->stream);
        }
        $this->header = rtrim($header);

        if (!preg_match('@^HTTP/1\.\d ([0-9]+) (\V+)@', $this->header, $matches)) {
            throw new HttpClientException('Missing status code in response header.');
        }

        $this->statusCode = (int) $matches[1];
        $this->statusMessage = $matches[2];

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
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the HTTP status message, e.g. "OK".
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * Returns wether the status is "200 OK".
     */
    public function isOk(): bool
    {
        return 200 == $this->statusCode;
    }

    /**
     * Returns wether the status class is "Informational".
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Returns wether the status class is "Success".
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Returns wether the status class is "Redirection".
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Returns wether the status class is "Client Error".
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Returns wether the status class is "Server Error".
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Returns whether the status is invalid.
     */
    public function isInvalid(): bool
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * Returns the header for the given key, or the entire header if no key is given.
     *
     * @param string|null $key Header key; if not set the entire header is returned
     *
     * @return ($key is null ? string : string|null)
     */
    public function getHeader(?string $key = null): ?string
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

        return null;
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

        return array_map(static function ($encoding): string {
            return trim(strtolower($encoding));
        }, explode(',', $contenEncodingHeader));
    }

    /**
     * Returns up to `$length` bytes from the body, or `false` if the end is reached.
     *
     * @param int $length Max number of bytes
     */
    public function getBufferedBody(int $length = 1024): false|string
    {
        if (feof($this->stream)) {
            return false;
        }

        if (!$this->streamFiltersInitialized) {
            if ($this->chunked) {
                if (!is_resource(stream_filter_append($this->stream, 'dechunk', STREAM_FILTER_READ))) {
                    throw new RuntimeException('Could not add dechunk filter to socket stream');
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
     */
    public function getBody(): string
    {
        if (null === $this->body) {
            $this->body = '';

            while (false !== ($buf = $this->getBufferedBody())) {
                $this->body .= $buf;
            }
        }

        return $this->body;
    }

    private function isGzipOrDeflateEncoded(): bool
    {
        $contentEncodings = $this->getContentEncodings();

        return in_array('gzip', $contentEncodings) || in_array('deflate', $contentEncodings);
    }

    /**
     * @param resource $stream
     * @return resource
     */
    private function addZlibStreamFilter($stream, int $mode)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('The stream has to be a resource.');
        }

        if (!in_array('zlib.*', stream_get_filters())) {
            throw new RuntimeException('The zlib filter for streams is missing.');
        }

        if (!in_array($mode, [STREAM_FILTER_READ, STREAM_FILTER_WRITE])) {
            throw new InvalidArgumentException('Invalid stream filter mode.');
        }

        $appendedZlibStreamFilter = stream_filter_append(
            $stream,
            'zlib.inflate',
            $mode,
            ['window' => 47],    // To detect gzip and zlib header
        );

        if (!is_resource($appendedZlibStreamFilter)) {
            throw new RuntimeException('Could not add stream filter for gzip support.');
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
    public function writeBodyTo($resource): bool
    {
        $close = false;
        if (is_string($resource) && Dir::create(dirname($resource))) {
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
