<?php

namespace Redaxo\Core\Tests\Filesystem;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use SplFileInfo;
use Traversable;
use UnexpectedValueException;

use function count;

/** @internal */
final class FinderTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::put($this->getPath('file1.txt'), '');
        File::put($this->getPath('file2.yml'), '');
        File::put($this->getPath('dir1/file3.txt'), '');
        File::put($this->getPath('dir2/file4.yml'), '');
        File::put($this->getPath('dir2/dir/file5.yml'), '');
        Dir::create($this->getPath('dir1/dir'));
        Dir::create($this->getPath('dir2/dir1'));
        Dir::create($this->getPath('dir'));
        File::put($this->getPath('.DS_Store'), '');
        File::put($this->getPath('dir1/Thumbs.db'), '');
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        Dir::delete($this->getPath());
    }

    public function getPath(string $file = ''): string
    {
        return Path::addonData('test', 'FinderTest/' . $file);
    }

    /**
     * @param Traversable<string, SplFileInfo> $iterator
     * @param list<string> $contains
     */
    private function assertIteratorContains(Traversable $iterator, array $contains): void
    {
        $array = iterator_to_array($iterator, true);
        self::assertCount(count($contains), $array);
        foreach ($contains as $file) {
            self::assertArrayHasKey($this->getPath($file), $array, $file . ' is in array');
        }
    }

    public function testDefault(): void
    {
        $iterator = Finder::factory($this->getPath());
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1', 'dir2', 'dir']);
    }

    public function testRecursive(): void
    {
        $iterator = Finder::factory($this->getPath())->recursive();
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1', 'dir1/file3.txt', 'dir1/dir', 'dir2', 'dir2/file4.yml', 'dir2/dir', 'dir2/dir/file5.yml', 'dir2/dir1', 'dir']);
    }

    public function testFilesOnly(): void
    {
        $iterator = Finder::factory($this->getPath())->recursive()->filesOnly();
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1/file3.txt', 'dir2/file4.yml', 'dir2/dir/file5.yml']);
    }

    public function testDirsOnly(): void
    {
        $iterator = Finder::factory($this->getPath())->recursive()->dirsOnly();
        $this->assertIteratorContains($iterator, ['dir1', 'dir1/dir', 'dir2', 'dir2/dir', 'dir2/dir1', 'dir']);
    }

    public function testIgnoreFiles(): void
    {
        $iterator = Finder::factory($this->getPath())
            ->recursive()
            ->ignoreFiles('*.txt', false)
            ->ignoreFiles(['file2.yml', 'file4*']);
        $this->assertIteratorContains($iterator, ['dir1', 'dir1/file3.txt', 'dir1/dir', 'dir2', 'dir2/dir', 'dir2/dir/file5.yml', 'dir2/dir1', 'dir']);
    }

    public function testIgnoreDirs(): void
    {
        $iterator = Finder::factory($this->getPath())
            ->recursive()
            ->ignoreDirs('dir', false)
            ->ignoreDirs('dir1');
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir2', 'dir2/file4.yml', 'dir2/dir', 'dir2/dir/file5.yml']);
    }

    public function testIgnoreSystemStuff(): void
    {
        $iterator = Finder::factory($this->getPath())->recursive()->ignoreSystemStuff(false);
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1', 'dir1/file3.txt', 'dir1/dir', 'dir2', 'dir2/file4.yml', 'dir2/dir', 'dir2/dir/file5.yml', 'dir2/dir1', 'dir', '.DS_Store', 'dir1/Thumbs.db']);
    }

    public function testIgnoreUnreadableDirsToleratesVanishingSubdir(): void
    {
        // Race condition: a sub-directory is listed by scandir() but disappears
        // before RecursiveDirectoryIterator descends into it (e.g. concurrent
        // rex_delete_cache() calls on a multi-worker PHP server).
        // With ignoreUnreadableDirs() the iteration must keep going instead of
        // throwing UnexpectedValueException.
        $sub = $this->getPath('dir2/dir');

        foreach (Finder::factory($this->getPath())->recursive()->ignoreUnreadableDirs() as $file) {
            if ($file->isDir() && (string) $file === rtrim($sub, '/')) {
                Dir::delete($sub);
            }
        }

        self::assertDirectoryDoesNotExist($sub);
    }

    public function testRecursiveThrowsOnVanishingSubdirByDefault(): void
    {
        // Default semantics: an unreadable/vanishing sub-directory surfaces as
        // UnexpectedValueException. Callers like backup/copy rely on this so
        // partial reads can't pass for success.
        $sub = $this->getPath('dir2/dir');

        $this->expectException(UnexpectedValueException::class);

        foreach (Finder::factory($this->getPath())->recursive() as $file) {
            if ($file->isDir() && (string) $file === rtrim($sub, '/')) {
                Dir::delete($sub);
            }
        }
    }
}
