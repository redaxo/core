<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_file_test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        rex_dir::create(self::getPath());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        rex_dir::delete(self::getPath());
    }

    private static function getPath(string $file = ''): string
    {
        return rex_path::addonData('tests', 'rex_file_test/' . $file);
    }

    public function testRequireThrows(): void
    {
        $this->expectException(rex_exception::class);

        $file = self::getPath('non_existing.txt');
        rex_file::require($file);
    }

    public function testGetDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertNull(rex_file::get($file), 'get() returns null for non-existing files');
        $myDefault = 'myDefault';
        self::assertEquals($myDefault, rex_file::get($file, $myDefault), 'get() returns given default value for non-existing files');
    }

    public function testGetConfigDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertEquals([], rex_file::getConfig($file), 'getConfig() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, rex_file::getConfig($file, $myDefault), 'getConfig() returns given default value for non-existing files');
    }

    public function testGetCacheDefault(): void
    {
        $file = self::getPath('non_existing.txt');
        self::assertEquals([], rex_file::getCache($file), 'getCache() returns empty array for non-existing files');
        $myDefault = ['myDefault'];
        self::assertEquals($myDefault, rex_file::getCache($file, $myDefault), 'getCache() returns given default value for non-existing files');
    }

    public function testPutGet(): void
    {
        $file = self::getPath('putget.txt');
        $content = 'test';
        self::assertTrue(rex_file::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, rex_file::get($file), 'get() returns content of file');
    }

    public function testPutGetConfig(): void
    {
        $file = self::getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(rex_file::putConfig($file, $content), 'putConfig() returns true on success');
        self::assertEquals($content, rex_file::getConfig($file), 'getConfig() returns content of file');
    }

    public function testPutGetCache(): void
    {
        $file = self::getPath('putgetcache.txt');
        $content = ['test', 'key' => 'value'];
        self::assertTrue(rex_file::putCache($file, $content), 'putCache() returns true on success');
        self::assertEquals($content, rex_file::getCache($file), 'getCache() returns content of file');
    }

    public function testPutInNewDir(): void
    {
        $file = self::getPath('subdir/test.txt');
        $content = 'test';
        self::assertTrue(rex_file::put($file, $content), 'put() returns true on success');
        self::assertEquals($content, rex_file::get($file), 'get() returns content of file');
    }

    public function testCopyToFile(): void
    {
        $orig = self::getPath('orig.txt');
        $copy = self::getPath('sub/copy.txt');
        $content = 'test';
        rex_file::put($orig, $content);
        self::assertTrue(rex_file::copy($orig, $copy), 'copy() returns true on success');
        self::assertEquals($content, rex_file::get($orig), 'content of copied file has not changed');
        self::assertEquals($content, rex_file::get($copy), 'content of new file is the same as of original file');
    }

    public function testCopyToDir(): void
    {
        $orig = self::getPath('file.txt');
        $copyDir = self::getPath('copy');
        $copyFile = self::getPath('copy/file.txt');
        $content = 'test';
        rex_file::put($orig, $content);
        rex_dir::create($copyDir);
        self::assertTrue(rex_file::copy($orig, $copyDir), 'copy() returns true on success');
        self::assertEquals($content, rex_file::get($copyFile), 'content of new file is the same as of original file');
    }

    public function testDelete(): void
    {
        $file = self::getPath('delete.txt');
        rex_file::put($file, '');
        self::assertFileExists($file, 'file exists after put()');
        self::assertTrue(rex_file::delete($file), 'delete() returns true on success');
        self::assertFileDoesNotExist($file, 'file does not exist after delete()');
        self::assertTrue(rex_file::delete($file), 'delete() returns true when the file is already deleted');
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
        self::assertEquals($expectedExtension, rex_file::extension($file), 'extension() returns file extension');
    }

    /** @return iterable<int, array{0: string, 1: string, 2?: string}> */
    public static function dataTestMimeType(): iterable
    {
        yield ['image/png', rex_path::pluginAssets('be_style', 'redaxo', 'icons/apple-touch-icon.png')];
        yield ['text/xml', rex_path::pluginAssets('be_style', 'redaxo', 'icons/browserconfig.xml')];
        yield ['text/css', rex_path::pluginAssets('be_style', 'redaxo', 'css/styles.css')];
        yield ['application/javascript', rex_path::pluginAssets('be_style', 'redaxo', 'javascripts/redaxo.js')];
        yield ['image/svg+xml', rex_path::addonAssets('be_style', 'images/redaxo-logo.svg')];

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
            rex_file::put($file, 'Hallo Welt');
        }

        self::assertEquals($expectedMimeType, rex_file::mimeType($file, $filename));
    }

    public function testGetOutput(): void
    {
        $file = self::getPath('test.php');
        rex_file::put($file, 'a<?php echo "b";');
        self::assertEquals('ab', rex_file::getOutput($file), 'getOutput() returns the executed content');
    }
}
