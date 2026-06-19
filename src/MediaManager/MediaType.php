<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Interfaces\ModifierInterface;
use Redaxo\Core\MediaManager\Attribute\AsMediaType;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;

/**
 * A media type describes how a media file is transformed before it is delivered.
 *
 * Types are plain classes registered via {@see AsMediaType}.
 * Inside {@see self::process()} the image is manipulated directly through the Intervention Image
 * API (`$context->image`) — reusable/complex manipulations live in dedicated
 * {@see ModifierInterface} classes applied via `$context->image->modify(...)`.
 *
 * Example:
 *
 *     #[AsMediaType('rex_media_small')]
 *     final class Small implements MediaType
 *     {
 *         public function process(MediaContext $context): void
 *         {
 *             $context->image->scaleDown(200, 200);
 *         }
 *     }
 */
interface MediaType
{
    /**
     * Transform the media described by the context.
     *
     * @throws MediaNotFoundException to abort processing and have the engine return a 404 response, e.g. when a source file or database record referenced by the type does not exist
     */
    public function process(MediaContext $context): void;
}
