<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Format;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;

use function is_file;
use function sprintf;

/**
 * Runs a {@see MediaType} against a source file and produces the {@see MediaResult} to deliver.
 *
 * This is the actual processing core: it builds the {@see MediaContext}, calls
 * {@see MediaType::process()} and encodes the (possibly mutated) image — or returns the raw file
 * when the type did not decode it as an image.
 *
 * @internal
 */
final readonly class MediaProcessor
{
    public function __construct(
        private ImageManagerInterface $manager,
    ) {}

    /**
     * @param Format|null $forceFormat Output format forced by the engine (e.g. content negotiation),
     *                                 taking precedence over the type's own format choice
     * @throws MediaNotFoundException if the type aborts or the source file to stream does not exist
     */
    public function render(MediaType $type, string $sourcePath, string $filename, ?Format $forceFormat = null): MediaResult
    {
        $context = new MediaContext($filename, $sourcePath, $this->manager);

        $type->process($context);

        if (!$context->isImageDecoded()) {
            if (!is_file($context->sourcePath)) {
                throw new MediaNotFoundException(sprintf('Source file "%s" does not exist.', $context->sourcePath));
            }

            $mediaType = File::mimeType($context->sourcePath) ?? 'application/octet-stream';

            return MediaResult::raw($context->sourcePath, $mediaType, $context->response);
        }

        $encoded = $this->encode($context, $forceFormat);

        return MediaResult::image((string) $encoded, $encoded->mediaType(), $context->response);
    }

    /** @throws MediaNotFoundException */
    private function encode(MediaContext $context, ?Format $forceFormat): EncodedImageInterface
    {
        // output format: forced (negotiation) > type override > source format
        $format = $forceFormat ?? $context->response->getFormat() ?? Format::tryCreate($context->sourceFormat);

        if (null === $format) {
            // unknown source extension: let Intervention figure it out
            return $context->image->encodeUsingFileExtension($context->sourceFormat);
        }

        return $context->image->encodeUsingFormat($format, ...$this->options($context, $format));
    }

    /**
     * Builds the encoder options for the given format, applying per-type overrides
     * ({@see MediaResponse}) before the configured defaults ({@see MediaQuality}).
     *
     * @return array<string, mixed>
     */
    private function options(MediaContext $context, Format $format): array
    {
        $options = [];

        $quality = $context->response->getQuality() ?? MediaQuality::get($format);
        if (null !== $quality) {
            $options['quality'] = $quality;
        }

        // Sensible default: progressive JPEG, baseline for everything else. A type may override it.
        $interlaced = $context->response->getInterlaced() ?? (Format::JPEG === $format);
        if ($interlaced) {
            // Intervention names the option differently per encoder
            if (Format::JPEG === $format) {
                $options['progressive'] = true;
            } elseif (Format::PNG === $format || Format::GIF === $format) {
                $options['interlaced'] = true;
            }
            // WebP/AVIF have no interlacing concept
        }

        return $options;
    }
}
