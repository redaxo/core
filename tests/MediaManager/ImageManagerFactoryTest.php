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

    public function testCanDecodeProbesActualDecodingCapability(): void
    {
        $manager = new ImageManager(new GdDriver());

        // JPEG/PNG must be decodable on any sane GD build
        self::assertTrue(ImageManagerFactory::canDecode($manager, 'jpg'));
        self::assertTrue(ImageManagerFactory::canDecode($manager, 'png'));

        // GD can never decode PDF
        self::assertFalse(ImageManagerFactory::canDecode($manager, 'pdf'));

        // an unknown extension yields no sample and must report false
        self::assertFalse(ImageManagerFactory::canDecode($manager, 'docx'));
    }

    public function testCanDecodeIsCaseInsensitive(): void
    {
        $manager = new ImageManager(new GdDriver());

        self::assertTrue(ImageManagerFactory::canDecode($manager, 'JPG'));
    }

    public function testCanDecodeMatchesRealDecodeForDelegateFormats(): void
    {
        // whatever the probe reports for delegate-dependent formats must match a real decode attempt,
        // so the test passes regardless of which driver/delegates the environment provides
        $manager = ImageManagerFactory::create();

        foreach (['pdf', 'psd', 'tiff'] as $extension) {
            $sample = $this->sample($extension, $manager);
            if (null === $sample) {
                // no sample obtainable on this driver -> probe must report false
                self::assertFalse(ImageManagerFactory::canDecode($manager, $extension), $extension);
                continue;
            }

            try {
                $manager->decode($sample);
                $reallyDecodes = true;
            } catch (Throwable) {
                $reallyDecodes = false;
            }

            self::assertSame($reallyDecodes, ImageManagerFactory::canDecode($manager, $extension), $extension);
        }
    }

    private function sample(string $extension, ImageManager $manager): ?string
    {
        $base64 = match ($extension) {
            'pdf' => 'JVBERi0xLjQKMSAwIG9iago8PCAvVHlwZSAvQ2F0YWxvZyAvUGFnZXMgMiAwIFIgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL1R5cGUgL1BhZ2VzIC9LaWRzIFszIDAgUl0gL0NvdW50IDEgPj4KZW5kb2JqCjMgMCBvYmoKPDwgL1R5cGUgL1BhZ2UgL1BhcmVudCAyIDAgUiAvTWVkaWFCb3ggWzAgMCAxIDFdID4+CmVuZG9iagp4cmVmCjAgNAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMDkgMDAwMDAgbiAKMDAwMDAwMDA1OCAwMDAwMCBuIAowMDAwMDAwMTE1IDAwMDAwIG4gCnRyYWlsZXIKPDwgL1NpemUgNCAvUm9vdCAxIDAgUiA+PgpzdGFydHhyZWYKMTgyCiUlRU9G',
            'psd' => 'OEJQUwABAAAAAAAAAAEAAAABAAAAAQAQAAEAAAAAAAAAAAAAAEIAAAA6AAEAAAAAAAAAAAAAAAEAAAABAAEAAAAAAAQ4QklNbm9ybf8AAQAAAAAMAAAAAAAAAAACTDEAAAD//wAAAAAAAP//',
            default => null,
        };

        if (null !== $base64) {
            return base64_decode($base64, true) ?: null;
        }

        try {
            $sample = (string) $manager->createImage(1, 1)->encodeUsingFileExtension($extension);

            return '' === $sample ? null : $sample;
        } catch (Throwable) {
            return null;
        }
    }
}
