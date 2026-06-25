<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Exceptions\ImageException;
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
        $format = $forceFormat ?? $context->response->format ?? Format::tryCreate($context->sourceFormat);

        try {
            if (null === $format) {
                // unknown source extension: let Intervention figure it out
                $label = $context->sourceFormat;
                $encoded = $context->image->encodeUsingFileExtension($context->sourceFormat);
            } else {
                $label = $format->name;
                $encoded = $context->image->encodeUsingFormat($format, ...$this->options($context, $format));
            }
        } catch (ImageException $e) {
            // The driver has no encoder for the chosen output format. When the format was only
            // implied by the source (e.g. the source is a PDF page that can never be re-encoded as
            // PDF, or a TIFF on a GD build), degrade to a universally supported raster format so a
            // preview is still produced. An explicitly forced/requested format failing is a real
            // error and surfaces as a 404 rather than a silent format switch.
            $requested = $forceFormat ?? $context->response->format;
            if (null !== $requested) {
                throw new MediaNotFoundException(sprintf('Encoding the image as "%s" failed; the image driver is missing an encoder for this format.', $requested->name), $e);
            }

            $label = Format::PNG->name;
            $encoded = $context->image->encodeUsingFormat(Format::PNG, ...$this->options($context, Format::PNG));
        }

        // Some drivers (e.g. Imagick with only the read delegate for AVIF) silently produce an empty
        // result instead of throwing. Never cache or deliver that as a successful image.
        if ('' === (string) $encoded) {
            throw new MediaNotFoundException(sprintf('Encoding the image as "%s" produced an empty result; the image driver is likely missing an encoder for this format.', $label));
        }

        return $encoded;
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

        $quality = $context->response->quality ?? MediaQuality::get($format);
        if (null !== $quality) {
            $options['quality'] = $quality;
        }

        // Sensible default: progressive JPEG, baseline for everything else. A type may override it.
        $interlaced = $context->response->interlaced ?? (Format::JPEG === $format);
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
