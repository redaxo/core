<?php

namespace Redaxo\Core\MediaManager\Type;

use Redaxo\Core\MediaManager\Attribute\AsMediaType;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaType;
use Redaxo\Core\MediaManager\NegotiatesFormat;
use Redaxo\Core\MediaManager\NegotiatesImageFormats;

/**
 * The built-in default media types: scales an image down to fit within a square bounding box,
 * keeping the aspect ratio, and serves AVIF/WebP to clients that accept them. One class, registered
 * three times with different sizes.
 *
 * Referenced by type name (`rex_media_small` etc.), not by class.
 *
 * @internal
 */
#[AsMediaType('rex_media_small', maxSize: 200)]
#[AsMediaType('rex_media_medium', maxSize: 600)]
#[AsMediaType('rex_media_large', maxSize: 1200)]
final readonly class ResizedImage implements MediaType, NegotiatesFormat
{
    use NegotiatesImageFormats;

    public function __construct(
        private int $maxSize,
    ) {}

    public function process(MediaContext $context): void
    {
        $context->image->scaleDown($this->maxSize, $this->maxSize);
    }
}
