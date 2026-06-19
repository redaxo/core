<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaTypeRegistry;
use Redaxo\Core\MediaManager\Type\ScaleDown;

use function base64_decode;

/** @internal */
final class MediaTypeTest extends TestCase
{
    public function testRegistryDiscoversDefaultTypes(): void
    {
        self::assertTrue(MediaTypeRegistry::has('rex_media_small'));
        // all three default types are backed by the same parametric class
        self::assertInstanceOf(ScaleDown::class, MediaTypeRegistry::get('rex_media_small'));
        self::assertInstanceOf(ScaleDown::class, MediaTypeRegistry::get('rex_media_medium'));
        self::assertInstanceOf(ScaleDown::class, MediaTypeRegistry::get('rex_media_large'));

        self::assertNull(MediaTypeRegistry::get('non_existing_type'));
        self::assertFalse(MediaTypeRegistry::has('non_existing_type'));
    }

    public function testSourceHashSharedPerClass(): void
    {
        $small = MediaTypeRegistry::sourceHash('rex_media_small');

        self::assertNotSame('', $small);
        self::assertSame($small, MediaTypeRegistry::sourceHash('rex_media_small'));
        // names backed by the same class share the source hash
        self::assertSame($small, MediaTypeRegistry::sourceHash('rex_media_large'));
        self::assertSame('', MediaTypeRegistry::sourceHash('non_existing_type'));
    }

    public function testParametricFamilyForwardsConstructorArguments(): void
    {
        $manager = new ImageManager(new Driver());

        foreach (['rex_media_small' => 200, 'rex_media_medium' => 600, 'rex_media_large' => 1200] as $type => $expectedWidth) {
            $context = new MediaContext('test.jpg', '/path/test.jpg', $manager);
            $context->image = $manager->createImage(2000, 1500);

            MediaTypeRegistry::get($type)?->process($context);

            // 2000x1500 scaled down to fit maxSize box -> width == maxSize
            self::assertSame($expectedWidth, $context->image->width(), $type);
        }
    }

    public function testDecodeAcceptsBinarySource(): void
    {
        $manager = new ImageManager(new Driver());
        $context = new MediaContext('test.png', '/path/test.png', $manager);

        // 1x1 px PNG
        $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');

        $context->decode($binary);

        self::assertTrue($context->isImageDecoded());
        self::assertSame(1, $context->image->width());
    }

    public function testNonImageMediaIsNotDecodedWhenOnlyResponseIsTouched(): void
    {
        $context = new MediaContext('doc.pdf', '/path/doc.pdf', new ImageManager(new Driver()));

        // a header-only type (e.g. force download) never accesses the image
        $context->response->forceDownload();

        self::assertFalse($context->isImageDecoded());
        self::assertTrue($context->response->isDownload());
    }

    public function testResponseOutputIntent(): void
    {
        $response = new MediaContext('test.jpg', '/path/test.jpg', new ImageManager(new Driver()))->response;

        self::assertNull($response->getFormat());
        self::assertNull($response->getQuality());
        self::assertSame([], $response->getHeaders());

        $response->setFormat(Format::WEBP)->setQuality(70)->noIndex()->forceDownload();

        self::assertSame(Format::WEBP, $response->getFormat());
        self::assertSame(70, $response->getQuality());
        self::assertTrue($response->isDownload());
        self::assertSame('test.jpg', $response->getDownloadFilename());

        $headers = $response->getHeaders();
        self::assertArrayHasKey('X-Robots-Tag', $headers);
        self::assertSame('noindex', $headers['X-Robots-Tag']);
    }
}
