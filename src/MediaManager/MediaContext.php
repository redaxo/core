<?php

namespace Redaxo\Core\MediaManager;

use finfo;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;

use function array_merge;
use function array_values;
use function getimagesize;
use function getimagesizefromstring;
use function in_array;
use function is_array;
use function is_file;
use function str_contains;
use function strlen;
use function strtolower;

use const FILEINFO_MIME_TYPE;

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
    /** Raster formats the drivers decode themselves, without external delegates */
    private const array RASTER_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/x-ms-bmp'];
    /**
     * Path to the source file. Assigning a new path switches the source (e.g. to a file outside the
     * media pool) and discards any image decoded so far.
     */
    public string $sourcePath {
        set(string $path) {
            $this->sourcePath = $path;
            $this->decodedImage = null;
        }
    }

    /** Format (file extension) of the current source, derived from {@see self::$sourcePath}. */
    public string $sourceFormat {
        get => strtolower(File::extension($this->sourcePath));
    }

    /** The working image. Decoded lazily from the current source on first read; assignable directly. */
    public ImageInterface $image {
        /** @throws MediaNotFoundException if the source cannot be decoded as an image */
        get => $this->decodedImage ??= $this->load($this->sourcePath);
        set {
            $this->decodedImage = $value;
        }
    }

    /** Backing store for {@see self::$image}; kept separate so {@see self::isImageDecoded()} can be queried without triggering decoding. */
    private ?ImageInterface $decodedImage = null;

    public readonly MediaResponse $response;

    /**
     * Formats the media manager may hand to the image driver, with the content types the file must actually have.
     *
     * Everything else — notably SVG, EPS/PS, MVG/MSL, plain text — never reaches the driver: ImageMagick picks its decoder
     * from the file *content*, so without this check any media pool file (a crafted EPS, an SVG with external references,
     * a text file starting with MVG commands …) would be fed to delegates like Ghostscript or rsvg by a single anonymous
     * request to `?rex_media_type=…&rex_media_file=…`.
     *
     * Addons can extend the list for further formats they deliberately want to rasterize.
     *
     * @var array<string, list<string>>
     */
    public static array $decodableFormats = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'jpe' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'], 'tif' => ['image/tiff'], 'tiff' => ['image/tiff'],
        'pdf' => ['application/pdf'], 'psd' => ['image/vnd.adobe.photoshop'],
    ];

    /** Upper limit for width × height of a source image (protects against decompression bombs) */
    public static int $maxPixels = 100_000_000;

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
    public function decode(string $source): ImageInterface
    {
        return $this->decodedImage = $this->load($source);
    }

    /** @throws MediaNotFoundException if the source cannot be decoded as an image */
    private function load(string $source): ImageInterface
    {
        $this->assertDecodable($source);

        try {
            return $this->manager->decode($source);
        } catch (ImageException $e) {
            throw new MediaNotFoundException('Could not decode media source as an image.', $e);
        }
    }

    /** @throws MediaNotFoundException if the source is not an allowed image format or too large */
    private function assertDecodable(string $source): void
    {
        $isPath = !str_contains($source, "\0") && strlen($source) < 4096 && is_file($source);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($isPath ? $finfo->file($source) : $finfo->buffer($source));

        // plain raster content (decoded by the driver itself, no delegates) is fine under any name; formats that need
        // delegates (PDF, PSD, TIFF …) only when the extension says so; everything else never
        $allowed = self::RASTER_MIME_TYPES;
        if ($isPath) {
            $allowed = [...$allowed, ...self::$decodableFormats[strtolower(File::extension($source))] ?? []];
        } else {
            $allowed = [...$allowed, ...array_merge(...array_values(self::$decodableFormats))];
        }
        if (!in_array($mime, $allowed, true)) {
            throw new MediaNotFoundException('Media source is not a decodable image format.');
        }

        $size = $isPath ? @getimagesize($source) : @getimagesizefromstring($source);
        if (is_array($size) && $size[0] * $size[1] > self::$maxPixels) {
            throw new MediaNotFoundException('Media source exceeds the maximum image size.');
        }
    }

    /** Whether the media was decoded as an image (i.e. `$image` was accessed or assigned). */
    public function isImageDecoded(): bool
    {
        return null !== $this->decodedImage;
    }
}
