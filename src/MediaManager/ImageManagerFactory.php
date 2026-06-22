<?php

namespace Redaxo\Core\MediaManager;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Redaxo\Core\Exception\RuntimeException;

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
