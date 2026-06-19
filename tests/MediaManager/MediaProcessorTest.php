<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\MediaManager\MediaContext;
use Redaxo\Core\MediaManager\MediaProcessor;
use Redaxo\Core\MediaManager\MediaResult;
use Redaxo\Core\MediaManager\MediaType;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/** @internal */
final class MediaProcessorTest extends TestCase
{
    private ImageManagerInterface $manager;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->manager = new ImageManager(new Driver());
        $this->tmpDir = sys_get_temp_dir() . '/mm_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testRendersImageWithFormatConversion(): void
    {
        $source = $this->tmpDir . '/source.png';
        $this->manager->createImage(400, 300)->save($source);

        $type = new class implements MediaType {
            public function process(MediaContext $context): void
            {
                $context->image->scaleDown(200, 200);
                $context->response->setFormat(Format::WEBP);
            }
        };

        $result = new MediaProcessor($this->manager)->render($type, $source, 'source.png');

        self::assertFalse($result->isRaw());
        self::assertSame('image/webp', $result->mediaType);
        self::assertNotNull($result->content);

        // decode the result back to confirm the actual pixels
        $rendered = $this->manager->decode($result->content);
        self::assertSame(200, $rendered->width());
        self::assertSame(150, $rendered->height());
    }

    public function testForcedFormatTakesPrecedenceOverTypeFormat(): void
    {
        $source = $this->tmpDir . '/source.png';
        $this->manager->createImage(100, 100)->save($source);

        // type asks for WEBP, but the engine forces AVIF (e.g. negotiated)
        $type = new class implements MediaType {
            public function process(MediaContext $context): void
            {
                $context->image->scaleDown(50, 50);
                $context->response->setFormat(Format::WEBP);
            }
        };

        $result = new MediaProcessor($this->manager)->render($type, $source, 'source.png', Format::AVIF);

        self::assertSame('image/avif', $result->mediaType);
    }

    public function testRawPassthroughForUndecodedMedia(): void
    {
        $source = $this->tmpDir . '/file.txt';
        file_put_contents($source, 'hello');

        $type = new class implements MediaType {
            public function process(MediaContext $context): void
            {
                $context->response->forceDownload();
            }
        };

        $result = new MediaProcessor($this->manager)->render($type, $source, 'file.txt');

        self::assertTrue($result->isRaw());
        self::assertSame($source, $result->sourcePath);
        self::assertTrue($result->response->isDownload());
    }

    public function testThrowsWhenRawSourceMissing(): void
    {
        $type = new class implements MediaType {
            public function process(MediaContext $context): void {}
        };

        $this->expectException(MediaNotFoundException::class);

        new MediaProcessor($this->manager)->render($type, $this->tmpDir . '/missing.bin', 'missing.bin');
    }

    public function testQualityOverrideAffectsEncoding(): void
    {
        $source = $this->tmpDir . '/photo.jpg';
        // a noisy image so quality actually changes the size
        $image = $this->manager->createImage(200, 200);
        for ($x = 0; $x < 200; $x += 2) {
            for ($y = 0; $y < 200; $y += 2) {
                $image->drawPixel($x, $y, sprintf('#%02x%02x%02x', ($x * 7) % 256, ($y * 11) % 256, ($x + $y) % 256));
            }
        }
        $image->save($source);

        $low = $this->renderJpegWithQuality($source, 10);
        $high = $this->renderJpegWithQuality($source, 95);

        self::assertNotNull($low->content);
        self::assertNotNull($high->content);
        self::assertLessThan(strlen($high->content), strlen($low->content));
    }

    private function renderJpegWithQuality(string $source, int $quality): MediaResult
    {
        $type = new class($quality) implements MediaType {
            public function __construct(
                private readonly int $quality,
            ) {}

            public function process(MediaContext $context): void
            {
                $context->image->scaleDown(150, 150);
                $context->response->setFormat(Format::JPEG)->setQuality($this->quality);
            }
        };

        return new MediaProcessor($this->manager)->render($type, $source, 'photo.jpg');
    }

    public function testInterlaceOverrideAffectsEncoding(): void
    {
        $source = $this->tmpDir . '/photo.jpg';
        $image = $this->manager->createImage(60, 60);
        for ($x = 0; $x < 60; ++$x) {
            for ($y = 0; $y < 60; ++$y) {
                $image->drawPixel($x, $y, sprintf('#%02x%02x80', ($x * 4) % 256, ($y * 4) % 256));
            }
        }
        $image->save($source);

        $default = $this->renderJpeg($source, null);
        $baseline = $this->renderJpeg($source, false);
        $progressive = $this->renderJpeg($source, true);

        self::assertNotNull($baseline->content);
        self::assertNotNull($progressive->content);
        // progressive JPEG has a different internal structure than the baseline one
        self::assertNotSame($baseline->content, $progressive->content);
        // default (no override) is progressive
        self::assertSame($progressive->content, $default->content);
    }

    private function renderJpeg(string $source, ?bool $interlaced): MediaResult
    {
        $type = new class($interlaced) implements MediaType {
            public function __construct(
                private readonly ?bool $interlaced,
            ) {}

            public function process(MediaContext $context): void
            {
                $context->image->scaleDown(60, 60);
                $context->response->setFormat(Format::JPEG);

                if (null !== $this->interlaced) {
                    $context->response->setInterlaced($this->interlaced);
                }
            }
        };

        return new MediaProcessor($this->manager)->render($type, $source, 'photo.jpg');
    }
}
