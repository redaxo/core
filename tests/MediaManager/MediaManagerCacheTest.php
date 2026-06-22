<?php

namespace Redaxo\Core\Tests\MediaManager;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\MediaManager\MediaManager;

use function dirname;
use function sys_get_temp_dir;
use function uniqid;

/** @internal */
final class MediaManagerCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rex-mm-cache-' . uniqid();
        MediaManager::setCacheDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        Dir::delete($this->dir);
    }

    public function testDeleteCacheRemovesFilesAndEmptiedDirectories(): void
    {
        // foto.jpg is cached in several type/hash dirs; "hashD" additionally holds an unrelated file
        $this->write('rex_media_small/hashA/foto.jpg');
        $this->write('rex_media_small/hashA/foto.jpg.meta');
        $this->write('rex_media_small/hashB/other.jpg'); // same type, different file -> hashB stays
        $this->write('rex_media_large/hashC/foto.jpg');
        $this->write('rex_media_large/hashC/foto.jpg.meta');
        $this->write('rex_media_medium/hashD/foto.jpg');
        $this->write('rex_media_medium/hashD/shared.jpg'); // hashD keeps a file -> dir stays

        $deleted = MediaManager::deleteCache('foto.jpg');

        // all foto.jpg variants (incl. .meta) across the three hash dirs are gone, others remain
        self::assertSame(5, $deleted);
        self::assertFileDoesNotExist($this->dir . '/rex_media_small/hashA/foto.jpg');
        self::assertFileDoesNotExist($this->dir . '/rex_media_large/hashC/foto.jpg.meta');
        self::assertFileExists($this->dir . '/rex_media_small/hashB/other.jpg');
        self::assertFileExists($this->dir . '/rex_media_medium/hashD/shared.jpg');

        // emptied hash dirs are removed; a type dir is removed once all its hash dirs are gone
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_small/hashA');
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_large/hashC');
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_large');

        // dirs that still hold files are kept
        self::assertDirectoryExists($this->dir . '/rex_media_small'); // hashB remains
        self::assertDirectoryExists($this->dir . '/rex_media_medium/hashD'); // shared.jpg remains
    }

    private function write(string $relative): void
    {
        $path = $this->dir . '/' . $relative;
        Dir::create(dirname($path));
        file_put_contents($path, 'x');
    }
}
