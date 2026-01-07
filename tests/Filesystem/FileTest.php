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

        Dir::create($this->getPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        Dir::delete($this->getPath());
    }

    private function getPath(string $file = ''): string
    {
        return Path::addonData('test', 'FileTest/' . $file);
    }

    public function testRequireThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $file = $this->getPath('non_existing.txt');
        File::require($file);
    }

    public function testGetDefault(): void
    {
        $file = $this->getPath('non_existing.txt');
        self::assertNull(File::get($file), 'get() returns null for non-existing files');
        $myDefault = 'myDefault';
        self::assertEquals($myDefault, File::get($file, $myDefault), 'get() returns given default value for non-existing files');
    }

    public function testGetConfigDefault(): void
    {
        $file = $this->getPath('non_existing.txt');
        self::assertEquals([], File::getConfig($file), 'getConfig() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, File::getConfig($file, $myDefault), 'getConfig() returns given default value for non-existing files');
    }

    public function testGetCacheDefault(): void
    {
        $file = $this->getPath('non_existing.txt');
        self::assertEquals([], File::getCache($file), 'getCache() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, File::getCache($file, $myDefault), 'getCache() returns given default value for non-existing files');
    }

    public function testPutGet(): void
    {
        $file = $this->getPath('putget.txt');
        $content = 'test';
        self::assertTrue(File::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, File::get($file), 'get() returns content of file');
    }

    public function testPutGetConfig(): void
    {
        $file = $this->getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(File::putConfig($file, $content), 'putConfig() returns true on success');
        self::assertEquals($content, File::getConfig($file), 'getConfig() returns content of file');
    }

    public function testPutGetCache(): void
    {
        $file = $this->getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(File::putCache($file, $content), 'putCache() returns true on success');
        self::assertEquals($content, File::getCache($file), 'getCache() returns content of file');
    }

    public function testPutInNewDir(): void
    {
        $file = $this->getPath('subdir/test.txt');
        $content = 'test';
        self::assertTrue(File::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, File::get($file), 'get() returns content of file');
    }

    public function testCopyToFile(): void
    {
        $orig = $this->getPath('orig.txt');
        $copy = $this->getPath('sub/copy.txt');
        $content = 'test';
        File::put($orig, $content);
        self::assertTrue(File::copy($orig, $copy), 'copy() returns true on success');
        self::assertEquals($content, File::get($orig), 'content of copied file has not changed');
        self::assertEquals($content, File::get($copy), 'content of new file is the same as of original file');
    }

    public function testCopyToDir(): void
    {
        $orig = $this->getPath('file.txt');
        $copyDir = $this->getPath('copy');
        $copyFile = $this->getPath('copy/file.txt');
        $content = 'test';
        File::put($orig, $content);
        Dir::create($copyDir);
        self::assertTrue(File::copy($orig, $copyDir), 'copy() returns true on success');
        self::assertEquals($content, File::get($copyFile), 'content of new file is the same as of original file');
    }

    public function testDelete(): void
    {
        $file = $this->getPath('delete.txt');
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

    /** @return list<array{string, string}> */
    public static function dataTestMimeType(): array
    {
        return [
            ['image/png', Path::coreAssets('icons/apple-touch-icon.png')],
            ['text/xml', Path::coreAssets('icons/browserconfig.xml')],
            ['text/css', Path::coreAssets('css/styles.css')],
            ['application/javascript', Path::coreAssets('js/redaxo.js')],
            ['image/svg+xml', Path::coreAssets('images/redaxo-logo.svg')],
        ];
    }

    #[DataProvider('dataTestMimeType')]
    public function testMimeType(string $expectedMimeType, string $file): void
    {
        self::assertEquals($expectedMimeType, File::mimeType($file));
    }

    public function testGetOutput(): void
    {
        $file = $this->getPath('test.php');
        File::put($file, 'a<?php echo "b";');
        self::assertEquals('ab', File::getOutput($file), 'getOutput() returns the executed content');
    }
}
