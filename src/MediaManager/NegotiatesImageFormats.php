<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;

/**
 * Default {@see NegotiatesFormat} implementation for image types: offers AVIF and WebP to clients
 * that accept them, falling back to the source format otherwise.
 *
 * Use it to opt a {@see MediaType} into content negotiation in one line:
 *
 *     final class Thumbnail implements MediaType, NegotiatesFormat
 *     {
 *         use NegotiatesImageFormats;
 *
 *         public function process(MediaContext $context): void { ... }
 *     }
 *
 * Implement {@see NegotiatesFormat::negotiableFormats()} directly instead if you need a different
 * candidate list.
 */
trait NegotiatesImageFormats
{
    /** @return list<Format> */
    public function negotiableFormats(): array
    {
        return [Format::AVIF, Format::WEBP];
    }
}
