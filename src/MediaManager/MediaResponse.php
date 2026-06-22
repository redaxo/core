<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

/**
 * The output side of a {@see MediaContext}: how the processed media is encoded and delivered.
 *
 * A {@see MediaType} configures it through the fluent setters; the engine reads the resulting state
 * via the public properties. A type may touch only the response (e.g. force a PDF download) without
 * ever decoding the media as an image.
 *
 * Content disposition and `Cache-Control` are modelled explicitly (rather than as raw headers) so
 * the engine can map them onto its delivery without producing duplicate headers; everything else
 * goes through {@see self::setHeader()}.
 */
final class MediaResponse
{
    /** Output format override; `null` keeps the source format. */
    public private(set) ?Format $format = null;

    /** Output quality override (0–100; 100 is lossless for WebP); `null` uses the format default. */
    public private(set) ?int $quality = null;

    /** Interlacing override (progressive JPEG / interlaced PNG/GIF); `null` uses the configured default. */
    public private(set) ?bool $interlaced = null;

    /** Whether the result is delivered as a download instead of inline. */
    public private(set) bool $download = false;

    /** Filename used when delivering as a download; falls back to the original media filename. */
    public string $downloadFilename {
        get => $this->customDownloadFilename ?? $this->filename;
    }

    /** Explicit `Cache-Control` header value; `null` lets the engine apply its default. */
    public private(set) ?string $cacheControl = null;

    /** @var array<string, string> Additional response headers (e.g. `X-Robots-Tag`). */
    public private(set) array $headers = [];

    private ?string $customDownloadFilename = null;

    /**
     * @param string $filename Default filename used when delivering as a download
     *
     * @internal
     */
    public function __construct(
        private readonly string $filename,
    ) {}

    /**
     * Force a specific output format (e.g. convert to WebP/AVIF, or PDF to JPEG).
     *
     * Pinning the format also makes {@see self::setQuality()} unambiguous. Without it, the engine
     * may negotiate the format per request; configure per-format defaults via {@see MediaQuality}.
     */
    public function setFormat(Format $format): self
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Override the output quality (0–100; 100 is lossless for WebP) for the resulting format.
     *
     * Intended for a type that pins the format via {@see self::setFormat()}; for negotiated output,
     * prefer per-format defaults via {@see MediaQuality}.
     */
    public function setQuality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /** Override interlacing (progressive JPEG / interlaced PNG/GIF) for the resulting format. */
    public function setInterlaced(bool $interlaced = true): self
    {
        $this->interlaced = $interlaced;

        return $this;
    }

    /** Deliver the result as a download instead of inline. */
    public function forceDownload(?string $filename = null): self
    {
        $this->download = true;
        $this->customDownloadFilename = $filename;

        return $this;
    }

    /** Set an explicit `Cache-Control` header value. */
    public function setCacheControl(string $value): self
    {
        $this->cacheControl = $value;

        return $this;
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
}
