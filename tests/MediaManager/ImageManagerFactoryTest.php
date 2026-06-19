<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\ImageManagerFactory;

/** @internal */
final class ImageManagerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ImageManagerFactory::setDriver(null);
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
}
