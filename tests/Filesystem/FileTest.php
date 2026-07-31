<?php

namespace Redaxo\Core\Tests\Filesystem;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

/** @internal */
final class FileTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Dir::create(self::getPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        Dir::delete(self::getPath());
    }

    private static function getPath(string $file = ''): string
    {
        return Path::addonData('test', 'FileTest/' . $file);
    }

    public function testRequireThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $file = self::getPath('non_existing.txt');
        File::require($file);
    }

    public function testGetDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertNull(File::get($file), 'get() returns null for non-existing files');
        $myDefault = 'myDefault';
        self::assertEquals($myDefault, File::get($file, $myDefault), 'get() returns given default value for non-existing files');
    }

    public function testGetConfigDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertEquals([], File::getConfig($file), 'getConfig() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, File::getConfig($file, $myDefault), 'getConfig() returns given default value for non-existing files');
    }

    public function testGetCacheDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertEquals([], File::getCache($file), 'getCache() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, File::getCache($file, $myDefault), 'getCache() returns given default value for non-existing files');
    }

    public function testPutGet(): void
    {
        $file = self::getPath('putget.txt');
        $content = 'test';
        self::assertTrue(File::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, File::get($file), 'get() returns content of file');
    }

    public function testPutGetConfig(): void
    {
        $file = self::getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(File::putConfig($file, $content), 'putConfig() returns true on success');
        self::assertEquals($content, File::getConfig($file), 'getConfig() returns content of file');
    }

    public function testPutGetCache(): void
    {
        $file = self::getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(File::putCache($file, $content), 'putCache() returns true on success');
        self::assertEquals($content, File::getCache($file), 'getCache() returns content of file');
    }

    public function testPutInNewDir(): void
    {
        $file = self::getPath('subdir/test.txt');
        $content = 'test';
        self::assertTrue(File::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, File::get($file), 'get() returns content of file');
    }

    public function testCopyToFile(): void
    {
        $orig = self::getPath('orig.txt');
        $copy = self::getPath('sub/copy.txt');
        $content = 'test';
        File::put($orig, $content);
        self::assertTrue(File::copy($orig, $copy), 'copy() returns true on success');
        self::assertEquals($content, File::get($orig), 'content of copied file has not changed');
        self::assertEquals($content, File::get($copy), 'content of new file is the same as of original file');
    }

    public function testCopyToDir(): void
    {
        $orig = self::getPath('file.txt');
        $copyDir = self::getPath('copy');
        $copyFile = self::getPath('copy/file.txt');
        $content = 'test';
        File::put($orig, $content);
        Dir::create($copyDir);
        self::assertTrue(File::copy($orig, $copyDir), 'copy() returns true on success');
        self::assertEquals($content, File::get($copyFile), 'content of new file is the same as of original file');
    }

    public function testDelete(): void
    {
        $file = self::getPath('delete.txt');
        File::put($file, '');
        self::assertFileExists($file, 'file exists after put()');
        self::assertTrue(File::delete($file), 'delete() returns true on success');
        self::assertFileDoesNotExist($file, 'file does not exist after delete()');
        self::assertTrue(File::delete($file), 'delete() returns true when the file is already deleted');
    }

    /** @return list<array{string, string}> */
    public static function dataTestExtension(): array
    {
        return [
            ['test.txt',      'txt'],
            ['test.file.txt', 'txt'],
            ['noextension',   ''],
            ['.hiddenfile',   'hiddenfile'],
        ];
    }

    #[DataProvider('dataTestExtension')]
    public function testExtension(string $file, string $expectedExtension): void
    {
        self::assertEquals($expectedExtension, File::extension($file), 'extension() returns file extension');
    }

    /** @return iterable<int, array{0: string, 1: string, 2?: string}> */
    public static function dataTestMimeType(): iterable
    {
        yield ['image/png', Path::coreAssets('icons/apple-touch-icon.png')];
        yield ['text/xml', Path::coreAssets('icons/browserconfig.xml')];
        yield ['text/css', Path::coreAssets('css/styles.css')];
        yield ['application/javascript', Path::coreAssets('js/redaxo.js')];
        yield ['image/svg+xml', Path::coreAssets('images/redaxo-logo.svg')];

        // simulates an uploaded file: extensionless tmp path, original name given separately
        // (created on demand in testMimeType(), since data providers run before setUp()/tearDown())
        $uploadTmpFile = self::getPath('upload_tmp_file');

        yield ['text/plain', $uploadTmpFile];
        yield ['text/vtt', $uploadTmpFile, 'test.vtt'];
        yield ['text/markdown', $uploadTmpFile, 'test.md'];
        yield ['text/plain', $uploadTmpFile, 'test.txt'];
    }

    #[DataProvider('dataTestMimeType')]
    public function testMimeType(string $expectedMimeType, string $file, ?string $filename = null): void
    {
        if (self::getPath('upload_tmp_file') === $file) {
            File::put($file, 'Hallo Welt');
        }

        self::assertEquals($expectedMimeType, File::mimeType($file, $filename));
    }

    public function testGetOutput(): void
    {
        $file = self::getPath('test.php');
        File::put($file, 'a<?php echo "b";');
        self::assertEquals('ab', File::getOutput($file), 'getOutput() returns the executed content');
    }
}
