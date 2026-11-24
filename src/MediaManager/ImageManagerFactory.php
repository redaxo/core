<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Throwable;

use function array_merge;
use function array_values;
use function base64_decode;
use function extension_loaded;
use function in_array;
use function strtolower;

/**
 * Creates the Intervention {@see ImageManager} with a usable driver.
 *
 * No image driver is a hard composer dependency. By default the driver is detected automatically,
 * preferring Imagick over GD. A specific driver — including the libvips driver from the optional
 * `intervention/image-driver-vips` package — can be selected explicitly via {@see self::$driver}.
 * If no driver is available a clear exception is thrown instead of a cryptic Intervention error.
 *
 * It also decides which sources may be handed to the driver at all: plain raster formats under any name, formats
 * needing an external delegate only as listed in {@see self::$delegateFormats}, nothing else.
 */
final class ImageManagerFactory
{
    /** Raster formats the drivers decode themselves, without external delegates (extension => content types) */
    private const array RASTER_FORMATS = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'jpe' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'], 'avif' => ['image/avif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
    ];
    /** @var class-string<DriverInterface>|null Driver to use; `null` detects one automatically */
    public static ?string $driver = null;

    /**
     * Formats beyond plain raster images that may be decoded, by extension, with the content types the file must have.
     *
     * Anything not listed here — notably SVG, EPS/PS, MVG/MSL — never reaches the driver: ImageMagick picks its decoder
     * from the file content and would otherwise feed any media pool file to delegates like Ghostscript or rsvg on an
     * anonymous media manager request. Listed formats run such delegates too (PDF: Ghostscript), so only add formats
     * that should really be rasterized.
     *
     * @var array<string, list<string>>
     */
    public static array $delegateFormats = [
        'tif' => ['image/tiff'], 'tiff' => ['image/tiff'],
        'pdf' => ['application/pdf'],
        'psd' => ['image/vnd.adobe.photoshop'],
    ];

    /** Upper limit for width × height of a source image (protects against decompression bombs) */
    public static int $maxPixels = 100_000_000;

    /** @var array<string, bool>|null Encode-capability probes (driver id + format => bool), lazily loaded from cache */
    private static ?array $encodeSupport = null;

    /** @var array<string, bool>|null Decode-capability probes (driver id + extension => bool), lazily loaded from cache */
    private static ?array $decodeSupport = null;

    /** @internal */
    public static function create(): ImageManager
    {
        $driver = null !== self::$driver ? new self::$driver() : self::detectDriver();

        return new ImageManager($driver);
    }

    /**
     * Whether the manager's driver can actually *encode* the given format.
     *
     * {@see DriverInterface::supports()} is unreliable for this: Imagick reports a format as
     * supported (via `queryFormats()`) even when only the read delegate is installed, so encoding
     * then silently yields an empty result. We therefore probe by encoding a 1×1 pixel image — an
     * empty result or an exception means the format cannot be produced.
     *
     * The probe is comparatively expensive (the first AVIF encode initializes the codec, ~80 ms on
     * Imagick), so results are persisted in the cache (keyed per driver and format) and only
     * computed once until the cache is cleared — keeping per-request overhead negligible.
     *
     * @internal
     */
    public static function canEncode(ImageManager $manager, Format $format): bool
    {
        if (null === self::$encodeSupport) {
            /** @var array<string, bool> $cached */
            $cached = File::getCache(self::encodeSupportCacheFile(), []) ?? [];
            self::$encodeSupport = $cached;
        }

        $key = $manager->driver->id() . ':' . $format->name;

        if (!isset(self::$encodeSupport[$key])) {
            self::$encodeSupport[$key] = self::probeEncode($manager, $format);
            File::putCache(self::encodeSupportCacheFile(), self::$encodeSupport);
        }

        return self::$encodeSupport[$key];
    }

    /**
     * Whether the manager's driver can actually *decode* a source with the given file extension —
     * i.e. whether a raster preview can be produced from it. Extensions that are neither a plain
     * raster format nor listed in {@see self::$delegateFormats} are never decodable.
     *
     * This is what separates "is conceptually an image" (a static extension list) from "the active
     * driver can rasterize this here and now". The answer is runtime-dependent: GD cannot decode
     * PDF/TIFF/PSD at all, and even Imagick only can when the matching delegate is installed
     * (Ghostscript for PDF, libtiff for TIFF, …) — which {@see DriverInterface::supports()} does not
     * reflect. We therefore probe by actually decoding a tiny sample of that format.
     *
     * The sample is either a shipped fixture (for decode-only formats Intervention cannot encode,
     * such as PDF and PSD) or, for symmetric raster formats, generated on the fly by encoding a 1×1
     * image as that extension. An unknown/unsupported extension yields no sample and thus `false`.
     *
     * Like {@see self::canEncode()} the probe is comparatively expensive (the first PDF decode spins
     * up Ghostscript), so results are persisted in the cache (keyed per driver and extension) and
     * only computed once until the cache is cleared.
     *
     * @internal
     */
    public static function canDecode(ImageManager $manager, string $extension): bool
    {
        $extension = strtolower($extension);

        if (!isset(self::RASTER_FORMATS[$extension]) && !isset(self::$delegateFormats[$extension])) {
            return false;
        }

        if (null === self::$decodeSupport) {
            /** @var array<string, bool> $cached */
            $cached = File::getCache(self::decodeSupportCacheFile(), []) ?? [];
            self::$decodeSupport = $cached;
        }

        $key = $manager->driver->id() . ':' . $extension;

        if (!isset(self::$decodeSupport[$key])) {
            self::$decodeSupport[$key] = self::probeDecode($manager, $extension);
            File::putCache(self::decodeSupportCacheFile(), self::$decodeSupport);
        }

        return self::$decodeSupport[$key];
    }

    /**
     * Whether a source with the given content type may be handed to the driver.
     *
     * @param string|null $extension Extension of the source file; `null` for a binary string, which then may have the
     *     content type of any allowed format
     *
     * @internal
     */
    public static function isDecodable(string $mimeType, ?string $extension): bool
    {
        $mimeTypes = array_merge(...array_values(self::RASTER_FORMATS));

        if (null === $extension) {
            $mimeTypes = [...$mimeTypes, ...array_merge(...array_values(self::$delegateFormats))];
        } else {
            $mimeTypes = [...$mimeTypes, ...self::$delegateFormats[strtolower($extension)] ?? []];
        }

        return in_array($mimeType, $mimeTypes, true);
    }

    /**
     * Reset the in-memory probe caches (mainly for tests).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$encodeSupport = null;
        self::$decodeSupport = null;
    }

    private static function encodeSupportCacheFile(): string
    {
        return Path::coreCache('media_manager/encode_support.json');
    }

    private static function decodeSupportCacheFile(): string
    {
        return Path::coreCache('media_manager/decode_support.json');
    }

    private static function probeEncode(ImageManager $manager, Format $format): bool
    {
        try {
            return '' !== (string) $manager->createImage(1, 1)->encodeUsingFormat($format);
        } catch (Throwable) {
            return false;
        }
    }

    private static function probeDecode(ImageManager $manager, string $extension): bool
    {
        $sample = self::probeSample($extension) ?? self::selfEncodedSample($manager, $extension);

        if (null === $sample) {
            return false;
        }

        try {
            $manager->decode($sample);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Minimal valid sample files for decode-only formats that Intervention cannot encode (so a
     * sample cannot be self-generated). Each is just large enough for the driver's delegate to
     * recognise and rasterise the format. Add an arm here to make a further format previewable.
     */
    private static function probeSample(string $extension): ?string
    {
        $base64 = match ($extension) {
            // 324-byte single empty page, valid xref — decoded by Imagick via the Ghostscript delegate
            'pdf' => 'JVBERi0xLjQKMSAwIG9iago8PCAvVHlwZSAvQ2F0YWxvZyAvUGFnZXMgMiAwIFIgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL1R5cGUgL1BhZ2VzIC9LaWRzIFszIDAgUl0gL0NvdW50IDEgPj4KZW5kb2JqCjMgMCBvYmoKPDwgL1R5cGUgL1BhZ2UgL1BhcmVudCAyIDAgUiAvTWVkaWFCb3ggWzAgMCAxIDFdID4+CmVuZG9iagp4cmVmCjAgNAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMDkgMDAwMDAgbiAKMDAwMDAwMDA1OCAwMDAwMCBuIAowMDAwMDAwMTE1IDAwMDAwIG4gCnRyYWlsZXIKPDwgL1NpemUgNCAvUm9vdCAxIDAgUiA+PgpzdGFydHhyZWYKMTgyCiUlRU9G',
            // 108-byte 1×1 grayscale Photoshop document
            'psd' => 'OEJQUwABAAAAAAAAAAEAAAABAAAAAQAQAAEAAAAAAAAAAAAAAEIAAAA6AAEAAAAAAAAAAAAAAAEAAAABAAEAAAAAAAQ4QklNbm9ybf8AAQAAAAAMAAAAAAAAAAACTDEAAAD//wAAAAAAAP//',
            default => null,
        };

        if (null === $base64) {
            return null;
        }

        return base64_decode($base64, true) ?: null;
    }

    /** Generate a 1×1 sample by encoding it as the given extension; `null` if the driver cannot encode it. */
    private static function selfEncodedSample(ImageManager $manager, string $extension): ?string
    {
        try {
            $sample = (string) $manager->createImage(1, 1)->encodeUsingFileExtension($extension);

            return '' === $sample ? null : $sample;
        } catch (Throwable) {
            return null;
        }
    }

    private static function detectDriver(): DriverInterface
    {
        if (extension_loaded('imagick')) {
            return new ImagickDriver();
        }

        if (extension_loaded('gd')) {
            return new GdDriver();
        }

        throw new RuntimeException('No image driver available. Install the GD or Imagick PHP extension, or set a driver explicitly via ImageManagerFactory::$driver (e.g. the libvips driver from "intervention/image-driver-vips").');
    }
}
