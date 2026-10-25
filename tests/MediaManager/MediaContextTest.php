<?php

namespace Redaxo\Core\Tests\MediaManager;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\MediaManager\MediaContext;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rename;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/** @internal */
final class MediaContextTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
    private const string EPS = "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 10 10\nshowpage\n";

    private ImageManager $manager;
    private string $tmpDir;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = new ImageManager(new Driver());
        $this->tmpDir = sys_get_temp_dir() . '/mm_context_' . uniqid();
        mkdir($this->tmpDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        MediaContext::$maxPixels = 100_000_000;

        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testDecodesRasterImagesRegardlessOfTheExtension(): void
    {
        $this->manager->createImage(20, 10)->save($this->tmpDir . '/image.png');
        $this->manager->createImage(20, 10)->save($this->tmpDir . '/real-png.png');
        rename($this->tmpDir . '/real-png.png', $this->tmpDir . '/real-png.jpg');

        self::assertSame(20, new MediaContext('image.png', $this->tmpDir . '/image.png', $this->manager)->image->width());
        self::assertSame(20, new MediaContext('real-png.jpg', $this->tmpDir . '/real-png.jpg', $this->manager)->image->width());
    }

    #[DataProvider('provideRejectsSourcesThatNeedDelegates')]
    public function testRejectsSourcesThatNeedDelegates(string $filename, string $content): void
    {
        $path = $this->tmpDir . '/' . $filename;
        file_put_contents($path, $content);

        // the source must never reach the driver (ImageMagick picks its decoder by the content)
        $manager = $this->createMock(ImageManagerInterface::class);
        $manager->expects(self::never())->method('decode');

        $this->expectException(MediaNotFoundException::class);

        $_ = new MediaContext($filename, $path, $manager)->image;
    }

    /** @return list<array{string, string}> */
    public static function provideRejectsSourcesThatNeedDelegates(): array
    {
        return [
            ['vector.eps', self::EPS],
            ['postscript.jpg', self::EPS],
            ['vector.svg', self::SVG],
            ['svg-disguised.jpg', self::SVG],
            ['notes.txt', "Hello\n"],
        ];
    }

    public function testDecodeChecksBinaryStrings(): void
    {
        $context = new MediaContext('blob.jpg', $this->tmpDir . '/missing.jpg', $this->manager);

        self::assertSame(20, $context->decode((string) $this->manager->createImage(20, 10)->encodeUsingFileExtension('png'))->width());

        $manager = $this->createMock(ImageManagerInterface::class);
        $manager->expects(self::never())->method('decode');

        $this->expectException(MediaNotFoundException::class);
        new MediaContext('blob.jpg', $this->tmpDir . '/missing.jpg', $manager)->decode(self::SVG);
    }

    public function testRejectsImagesExceedingMaxPixels(): void
    {
        $this->manager->createImage(20, 10)->save($this->tmpDir . '/image.png');
        MediaContext::$maxPixels = 199;

        $this->expectException(MediaNotFoundException::class);

        $_ = new MediaContext('image.png', $this->tmpDir . '/image.png', $this->manager)->image;
    }
}
