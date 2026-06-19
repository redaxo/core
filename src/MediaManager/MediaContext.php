<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Stringable;

use function strtolower;

/**
 * The single touch point a {@see MediaType} works with.
 *
 * It carries metadata about the source file, lazy access to the decoded Intervention image and the
 * {@see MediaResponse} describing how the result is delivered.
 *
 * The image is decoded lazily on first access to `$image`: a type that only manipulates the
 * response (e.g. forcing a download of a non-image file like a PDF) never triggers decoding, and
 * the engine streams the raw source file. A type may also supply its own source — a different file
 * (e.g. from a non-public directory) by assigning {@see self::$sourcePath}, or arbitrary content (a
 * file path or binary string, e.g. a database BLOB) via {@see self::decode()}, or assign an image
 * directly to {@see self::$image}.
 *
 * The image is mutated in place via the Intervention API (`$context->image->scaleDown(...)` etc.).
 */
final class MediaContext
{
    /** Backing store for the lazily decoded image; kept separate so {@see self::isImageDecoded()} can be queried without triggering decoding. */
    private ?ImageInterface $decoded = null;

    /**
     * Path to the source file. Assigning a new path switches the source (e.g. to a file outside the
     * media pool) and discards any image decoded so far.
     */
    public string $sourcePath {
        set(string $path) {
            $this->sourcePath = $path;
            $this->decoded = null;
        }
    }

    /** Format (file extension) of the current source, derived from {@see self::$sourcePath}. */
    public string $sourceFormat {
        get => strtolower(File::extension($this->sourcePath));
    }

    /** The working image. Decoded lazily from the current source on first read; assignable directly. */
    public ImageInterface $image {
        /** @throws MediaNotFoundException if the source cannot be decoded as an image */
        get => $this->decoded ??= $this->load($this->sourcePath);
        set {
            $this->decoded = $value;
        }
    }

    public readonly MediaResponse $response;

    /**
     * @param string $filename Original media filename (basename), e.g. `image.jpg`
     * @param string $sourcePath Path to the source file
     *
     * @internal the engine builds the context; a {@see MediaType} only receives it
     */
    public function __construct(
        public readonly string $filename,
        string $sourcePath,
        private readonly ImageManagerInterface $manager,
    ) {
        $this->sourcePath = $sourcePath;
        $this->response = new MediaResponse($filename);
    }

    /**
     * Decode an arbitrary source — a file path or a binary string (e.g. a database BLOB) — and use
     * it as the working image.
     *
     * @throws MediaNotFoundException if the source cannot be decoded as an image
     */
    public function decode(string|Stringable $source): ImageInterface
    {
        return $this->decoded = $this->load($source);
    }

    /** @throws MediaNotFoundException if the source cannot be decoded as an image */
    private function load(string|Stringable $source): ImageInterface
    {
        try {
            return $this->manager->decode($source);
        } catch (ImageException $e) {
            throw new MediaNotFoundException('Could not decode media source as an image.', $e);
        }
    }

    /** Whether the media was decoded as an image (i.e. `$image` was accessed or assigned). */
    public function isImageDecoded(): bool
    {
        return null !== $this->decoded;
    }
}
