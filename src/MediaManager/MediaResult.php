<?php

namespace Redaxo\Core\MediaManager;

/**
 * The outcome of processing a media type: either freshly encoded image bytes or a raw file to be
 * streamed as-is (for media a type chose not to decode as an image).
 *
 * @internal
 */
final readonly class MediaResult
{
    /**
     * @param string|null $content Encoded image bytes, or `null` for a raw file
     * @param string|null $sourcePath Path of the raw file to stream, or `null` for encoded content
     * @param string $mediaType Content type of the result
     */
    private function __construct(
        public ?string $content,
        public ?string $sourcePath,
        public string $mediaType,
        public MediaResponse $response,
    ) {}

    public static function image(string $content, string $mediaType, MediaResponse $response): self
    {
        return new self($content, null, $mediaType, $response);
    }

    public static function raw(string $sourcePath, string $mediaType, MediaResponse $response): self
    {
        return new self(null, $sourcePath, $mediaType, $response);
    }

    public function isRaw(): bool
    {
        return null !== $this->sourcePath;
    }

    /**
     * Serializable delivery metadata, persisted alongside the cache file so a cache hit can send
     * the same headers without re-running the type.
     *
     * @return array{mediaType: string, download: bool, downloadFilename: string, cacheControl: string|null, headers: array<string, string>}
     */
    public function meta(): array
    {
        return [
            'mediaType' => $this->mediaType,
            'download' => $this->response->download,
            'downloadFilename' => $this->response->downloadFilename,
            'cacheControl' => $this->response->cacheControl,
            'headers' => $this->response->headers,
        ];
    }
}
