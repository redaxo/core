<?php

namespace Redaxo\Core\Tests\MediaPool;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaPool\MediaHandler;
use ReflectionMethod;

/** @internal */
final class MediaHandlerTest extends TestCase
{
    private const EVIL_SVG = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="1" onclick="alert(2)"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        Dir::create(self::getPath());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Dir::delete(self::getPath());
        Dir::delete(self::getTempPath());
    }

    private static function getPath(string $file = ''): string
    {
        return Path::coreData('tests/MediaHandlerTest/' . $file);
    }

    private static function getTempPath(): string
    {
        return Path::coreCache('mediapool/sanitize');
    }

    private static function moveMedia(string $srcFile, string $dstFile, ?string $type): void
    {
        new ReflectionMethod(MediaHandler::class, 'moveMedia')->invoke(null, $srcFile, $dstFile, $type);
    }

    private static function assertSanitized(string $file): void
    {
        $content = File::get($file);

        self::assertIsString($content);
        self::assertStringNotContainsString('<script', $content);
        self::assertStringNotContainsString('onclick', $content);
        self::assertStringContainsString('<circle', $content);
    }

    public function testMoveSanitizesSvg(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        File::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, 'image/svg+xml');

        self::assertSanitized($dstFile);
        self::assertFileDoesNotExist($srcFile);
    }

    /**
     * Uploads are sanitized before they are moved, so the source file is a php upload temp file without extension.
     * The svg must be detected via the mime type or via the extension of the target file.
     */
    public function testMoveSanitizesSvgWithoutSourceExtension(): void
    {
        $srcFile = self::getPath('phpUpl04d');
        $dstFile = self::getPath('target.svg');
        File::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, null);

        self::assertSanitized($dstFile);
    }

    public function testMoveSanitizesSvgInPlace(): void
    {
        $file = self::getPath('inplace.svg');
        File::put($file, self::EVIL_SVG);

        self::moveMedia($file, $file, 'image/svg+xml');

        self::assertSanitized($file);
    }

    public function testMoveRejectsUnparsableSvg(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        File::put($srcFile, '<svg><unclosed &&&');

        try {
            self::moveMedia($srcFile, $dstFile, 'image/svg+xml');
            self::fail('Expected an ApiFunctionException');
        } catch (ApiFunctionException) {
            // expected
        }

        self::assertFileDoesNotExist($dstFile);
    }

    public function testMoveKeepsOtherFilesUntouched(): void
    {
        $srcFile = self::getPath('source.txt');
        $dstFile = self::getPath('target.txt');
        File::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, 'text/plain');

        self::assertSame(self::EVIL_SVG, File::get($dstFile));
        self::assertFileDoesNotExist($srcFile);
    }

    public function testMoveRemovesTempFile(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        File::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, 'image/svg+xml');

        self::assertSame([], glob(self::getTempPath() . '/*') ?: []);
    }
}
