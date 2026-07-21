<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_finder_test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        rex_file::put($this->getPath('file1.txt'), '');
        rex_file::put($this->getPath('file2.yml'), '');
        rex_file::put($this->getPath('dir1/file3.txt'), '');
        rex_file::put($this->getPath('dir2/file4.yml'), '');
        rex_file::put($this->getPath('dir2/dir/file5.yml'), '');
        rex_dir::create($this->getPath('dir1/dir'));
        rex_dir::create($this->getPath('dir2/dir1'));
        rex_dir::create($this->getPath('dir'));
        rex_file::put($this->getPath('.DS_Store'), '');
        rex_file::put($this->getPath('dir1/Thumbs.db'), '');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        rex_dir::delete($this->getPath());
    }

    public function getPath(string $file = ''): string
    {
        return rex_path::addonData('tests', 'rex_finder_test/' . $file);
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
        $iterator = rex_finder::factory($this->getPath());
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1', 'dir2', 'dir']);
    }

    public function testRecursive(): void
    {
        $iterator = rex_finder::factory($this->getPath())->recursive();
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1', 'dir1/file3.txt', 'dir1/dir', 'dir2', 'dir2/file4.yml', 'dir2/dir', 'dir2/dir/file5.yml', 'dir2/dir1', 'dir']);
    }

    public function testFilesOnly(): void
    {
        $iterator = rex_finder::factory($this->getPath())->recursive()->filesOnly();
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir1/file3.txt', 'dir2/file4.yml', 'dir2/dir/file5.yml']);
    }

    public function testDirsOnly(): void
    {
        $iterator = rex_finder::factory($this->getPath())->recursive()->dirsOnly();
        $this->assertIteratorContains($iterator, ['dir1', 'dir1/dir', 'dir2', 'dir2/dir', 'dir2/dir1', 'dir']);
    }

    public function testIgnoreFiles(): void
    {
        $iterator = rex_finder::factory($this->getPath())
            ->recursive()
            ->ignoreFiles('*.txt', false)
            ->ignoreFiles(['file2.yml', 'file4*']);
        $this->assertIteratorContains($iterator, ['dir1', 'dir1/file3.txt', 'dir1/dir', 'dir2', 'dir2/dir', 'dir2/dir/file5.yml', 'dir2/dir1', 'dir']);
    }

    public function testIgnoreDirs(): void
    {
        $iterator = rex_finder::factory($this->getPath())
            ->recursive()
            ->ignoreDirs('dir', false)
            ->ignoreDirs('dir1');
        $this->assertIteratorContains($iterator, ['file1.txt', 'file2.yml', 'dir2', 'dir2/file4.yml', 'dir2/dir', 'dir2/dir/file5.yml']);
    }

    public function testIgnoreSystemStuff(): void
    {
        $iterator = rex_finder::factory($this->getPath())->recursive()->ignoreSystemStuff(false);
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

        foreach (rex_finder::factory($this->getPath())->recursive()->ignoreUnreadableDirs() as $file) {
            if ($file->isDir() && (string) $file === rtrim($sub, '/')) {
                rex_dir::delete($sub);
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

        foreach (rex_finder::factory($this->getPath())->recursive() as $file) {
            if ($file->isDir() && (string) $file === rtrim($sub, '/')) {
                rex_dir::delete($sub);
            }
        }
    }
}
