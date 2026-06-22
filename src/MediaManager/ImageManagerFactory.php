<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Redaxo\Core\Exception\RuntimeException;
use Throwable;

use function extension_loaded;

/**
 * Creates the Intervention {@see ImageManager} with a usable driver.
 *
 * No image driver is a hard composer dependency. By default the driver is detected automatically,
 * preferring Imagick over GD. A specific driver — including the libvips driver from the optional
 * `intervention/image-driver-vips` package — can be selected explicitly via {@see self::setDriver()}.
 * If no driver is available a clear exception is thrown instead of a cryptic Intervention error.
 */
final class ImageManagerFactory
{
    /** @var class-string<DriverInterface>|null */
    private static ?string $driver = null;

    /** @var array<string, bool> Cached encode-capability probes, keyed by driver id + format */
    private static array $encodeSupport = [];

    /** @param class-string<DriverInterface>|null $driver `null` restores automatic detection */
    public static function setDriver(?string $driver): void
    {
        self::$driver = $driver;
    }

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
     * empty result or an exception means the format cannot be produced. Results are cached per
     * driver and format.
     *
     * @internal
     */
    public static function canEncode(ImageManager $manager, Format $format): bool
    {
        $key = $manager->driver->id() . ':' . $format->name;

        return self::$encodeSupport[$key] ??= self::probeEncode($manager, $format);
    }

    private static function probeEncode(ImageManager $manager, Format $format): bool
    {
        try {
            return '' !== (string) $manager->createImage(1, 1)->encodeUsingFormat($format);
        } catch (Throwable) {
            return false;
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

        throw new RuntimeException('No image driver available. Install the GD or Imagick PHP extension, or set a driver explicitly via ImageManagerFactory::setDriver() (e.g. the libvips driver from "intervention/image-driver-vips").');
    }
}
