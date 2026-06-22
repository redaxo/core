<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\ImageManagerFactory;
use Throwable;

/** @internal */
final class ImageManagerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ImageManagerFactory::setDriver(null);
        ImageManagerFactory::reset();
    }

    public function testCreateReturnsUsableManager(): void
    {
        self::assertSame(3, ImageManagerFactory::create()->createImage(3, 3)->width());
    }

    public function testExplicitDriverIsUsed(): void
    {
        ImageManagerFactory::setDriver(GdDriver::class);

        self::assertSame(5, ImageManagerFactory::create()->createImage(5, 5)->width());
    }

    public function testCanEncodeProbesActualEncodingCapability(): void
    {
        $manager = new ImageManager(new GdDriver());

        // JPEG/PNG must be encodable on any sane GD build
        self::assertTrue(ImageManagerFactory::canEncode($manager, Format::JPEG));
        self::assertTrue(ImageManagerFactory::canEncode($manager, Format::PNG));

        // AVIF/WebP depend on the build: whatever the probe reports must match a real encode attempt
        foreach ([Format::AVIF, Format::WEBP] as $format) {
            try {
                $reallyEncodes = '' !== (string) $manager->createImage(1, 1)->encodeUsingFormat($format);
            } catch (Throwable) {
                $reallyEncodes = false;
            }
            self::assertSame($reallyEncodes, ImageManagerFactory::canEncode($manager, $format), $format->name);
        }
    }
}
