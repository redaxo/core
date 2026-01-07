<?php

namespace Redaxo\Core\Tests\Filesystem;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

/** @internal */
final class DirTest extends TestCase
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
        return Path::addonData('test', 'DirTest/' . $file);
    }

    public function testCreate(): void
    {
        $path = $this->getPath('create');
        self::assertTrue(Dir::create($path), 'create() returns true on success');
        self::assertDirectoryExists($path, 'dir exists after create()');
        self::assertTrue(Dir::create($path), 'create() on existing dirs returns also true');
    }

    public function testCreateRecursive(): void
    {
        $path = $this->getPath('create_recursive/test/test');
        self::assertTrue(Dir::create($path), 'create() returns true on success');
        self::assertDirectoryExists($path, 'dir exists after create()');
    }

    public function testCopyToNewDir(): void
    {
        $orig = $this->getPath('orig1');
        $copy = $this->getPath('copy1');
        Dir::create($orig . '/dir1');
        File::put($orig . '/file.txt', '');
        File::put($orig . '/dir2/file.txt', '');

        self::assertTrue(Dir::copy($orig, $copy), 'copy() returns true on success');
        self::assertDirectoryExists($copy . '/dir1', 'subdir exists after copy()');
        self::assertTrue(is_file($copy . '/file.txt'), 'file exists after copy()');
        self::assertTrue(is_file($copy . '/dir2/file.txt'), 'file in subdir exists after copy()');
    }

    public function testCopyToExistingDir(): void
    {
        $orig = $this->getPath('orig2');
        $copy = $this->getPath('copy2');
        // dir1 only in /orig
        Dir::create($orig . '/dir1');
        // dir2 only in /copy
        Dir::create($copy . '/dir2');
        // file1 only in /orig
        File::put($orig . '/file1.txt', '');
        File::put($orig . '/dir3/file1.txt', '');
        // file2 with newest version in /orig
        File::put($copy . '/file2.txt', 'file2_old');
        touch($copy . '/file2.txt', 1);
        File::put($copy . '/dir3/file2.txt', 'file2_old');
        touch($copy . '/dir3/file2.txt', 1);
        File::put($orig . '/file2.txt', 'file2_new');
        File::put($orig . '/dir3/file2.txt', 'file2_new');

        self::assertTrue(Dir::copy($orig, $copy), 'copy() returns true on success');
        self::assertDirectoryExists($copy . '/dir1', 'subdir of source dir exists in destination dir');
        self::assertDirectoryExists($copy . '/dir2', 'existsing subdir of destination dir still exists');
        self::assertTrue(is_file($copy . '/file1.txt'), 'file of source dir exists in destination dir');
        self::assertTrue(is_file($copy . '/dir3/file1.txt'), 'existing file of destination dir still exists');
        self::assertEquals('file2_new', File::get($copy . '/file2.txt'), 'existing file in destination dir will be replaced');
        self::assertEquals('file2_new', File::get($copy . '/dir3/file2.txt'), 'existing file in destination dir will be replaced');
    }

    public function testDeleteComplete(): void
    {
        $dir = $this->getPath('deleteComplete');
        $file = $this->getPath('deleteComplete/subdir/file.txt');
        File::put($file, '');

        self::assertTrue(is_file($file), 'file exists after put()');
        self::assertTrue(Dir::delete($dir), 'delete() returns true on success');
        self::assertDirectoryDoesNotExist($dir, 'dir does not exist after complete delete()');
    }

    public function testDeleteWithoutSelf(): void
    {
        $dir = $this->getPath('deleteCompleteWithoutSelf');
        $file = $this->getPath('deleteCompleteWithoutSelf/subdir/file.txt');
        File::put($file, '');

        self::assertTrue(is_file($file), 'file exists after put()');
        self::assertTrue(Dir::delete($dir, false), 'delete() returns true on success');
        self::assertFalse(is_file($file), 'file does not exist after delete()');
        self::assertDirectoryDoesNotExist($dir . '/subdir', 'subdir does not exist after delete()');
        self::assertDirectoryExists($dir, 'main dir still exists after delete() without self');
    }

    public function testDeleteFilesNotRecursive(): void
    {
        $dir = $this->getPath('deleteFilesNotRecursive');
        $file1 = $this->getPath('deleteFilesNotRecursive/file.txt');
        $file2 = $this->getPath('deleteFilesNotRecursive/subdir/file.txt');
        File::put($file1, '');
        File::put($file2, '');

        self::assertTrue(is_file($file1), 'file exists after put()');
        self::assertTrue(is_file($file2), 'file exists after put()');
        self::assertTrue(Dir::deleteFiles($dir, false), 'deleteFiles() returns true on success');
        self::assertFalse(is_file($file1), 'file in main dir does not exist after deleteFiles()');
        self::assertTrue(is_file($file2), 'file in subdir still exists after non-recursive deleteFiles()');
    }

    public function testDeleteFilesRecursive(): void
    {
        $dir = $this->getPath('deleteFilesRecursive');
        $file1 = $this->getPath('deleteFilesRecursive/file.txt');
        $file2 = $this->getPath('deleteFilesRecursive/subdir/file.txt');
        File::put($file1, '');
        File::put($file2, '');

        self::assertTrue(is_file($file1), 'file exists after put()');
        self::assertTrue(is_file($file2), 'file exists after put()');
        self::assertTrue(Dir::deleteFiles($dir), 'deleteFiles() returns true on success');
        self::assertFalse(is_file($file1), 'file in main dir does not exist after deleteFiles()');
        self::assertFalse(is_file($file2), 'file in subdir does not exist after recursive deleteFiles()');
        self::assertDirectoryExists($dir . '/subdir', 'subdir still exists after deleteFiles()');
    }
}
