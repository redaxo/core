<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

/**
 * The output side of a {@see MediaContext}: how the processed media is encoded and delivered.
 *
 * A {@see MediaType} may touch only the response (e.g. force a PDF download) without ever
 * decoding the media as an image.
 *
 * Content disposition and `Cache-Control` are modelled explicitly (rather than as raw headers) so
 * the engine can map them onto its delivery without producing duplicate headers; everything else
 * goes through {@see self::setHeader()}.
 *
 * A {@see MediaType} uses the setters; the getters are read by the engine only.
 */
final class MediaResponse
{
    /** Output format override; `null` keeps the source format. */
    private ?Format $format = null;

    /** Output quality override (1–100); `null` uses the configured default for the format. */
    private ?int $quality = null;

    /** Interlacing override (progressive JPEG / interlaced PNG/GIF); `null` uses the configured default. */
    private ?bool $interlaced = null;

    private bool $download = false;

    private ?string $downloadFilename = null;

    private ?string $cacheControl = null;

    /** @var array<string, string> Additional response headers (e.g. `X-Robots-Tag`). */
    private array $headers = [];

    /**
     * @param string $filename Default filename used when delivering as a download
     *
     * @internal
     */
    public function __construct(
        private readonly string $filename,
    ) {}

    /** Force a specific output format (e.g. convert to WebP/AVIF, or PDF to JPEG). */
    public function setFormat(Format $format): self
    {
        $this->format = $format;

        return $this;
    }

    /** @internal */
    public function getFormat(): ?Format
    {
        return $this->format;
    }

    /** Override the output quality (1–100) for the resulting format. */
    public function setQuality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /** @internal */
    public function getQuality(): ?int
    {
        return $this->quality;
    }

    /** Override interlacing (progressive JPEG / interlaced PNG/GIF) for the resulting format. */
    public function setInterlaced(bool $interlaced = true): self
    {
        $this->interlaced = $interlaced;

        return $this;
    }

    /** @internal */
    public function getInterlaced(): ?bool
    {
        return $this->interlaced;
    }

    /** Deliver the result as a download instead of inline. */
    public function forceDownload(?string $filename = null): self
    {
        $this->download = true;
        $this->downloadFilename = $filename;

        return $this;
    }

    /** @internal */
    public function isDownload(): bool
    {
        return $this->download;
    }

    /** @internal */
    public function getDownloadFilename(): string
    {
        return $this->downloadFilename ?? $this->filename;
    }

    public function setCacheControl(string $value): self
    {
        $this->cacheControl = $value;

        return $this;
    }

    /** @internal */
    public function getCacheControl(): ?string
    {
        return $this->cacheControl;
    }

    /** Ask search engines not to index the delivered file. */
    public function noIndex(): self
    {
        return $this->setHeader('X-Robots-Tag', 'noindex');
    }

    /** Set an additional response header (do not use for Content-Disposition or Cache-Control). */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return array<string, string>
     *
     * @internal
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
