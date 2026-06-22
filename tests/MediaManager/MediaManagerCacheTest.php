<?php

namespace Redaxo\Core\Tests\MediaManager;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\MediaManager\MediaManager;

use function dirname;
use function file_put_contents;
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

    public function testDeleteCacheForFilenameDropsItsWholeDirAndEmptiedTypeDir(): void
    {
        // cache layout: {type}/{filename}/{hash} -- foto.jpg has several hashes (variants/versions)
        $this->write('rex_media_small/foto.jpg/hashA');
        $this->write('rex_media_small/foto.jpg/hashA.meta');
        $this->write('rex_media_small/foto.jpg/hashV2'); // another variant of the same file
        $this->write('rex_media_small/other.jpg/hashB'); // different file, same type -> type dir stays
        $this->write('rex_media_large/foto.jpg/hashC');
        $this->write('rex_media_large/foto.jpg/hashC.meta');

        $deleted = MediaManager::deleteCache('foto.jpg');

        // every hash/variant of foto.jpg across all types is gone in one go
        self::assertSame(5, $deleted);
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_small/foto.jpg');
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_large/foto.jpg');

        // unrelated file is untouched, its type dir survives
        self::assertFileExists($this->dir . '/rex_media_small/other.jpg/hashB');
        self::assertDirectoryExists($this->dir . '/rex_media_small');

        // the type dir that held only foto.jpg is removed too -- no empty orphan
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_large');
    }

    public function testDeleteCacheWithoutFilenameClearsEverything(): void
    {
        $this->write('rex_media_small/foto.jpg/hashA');
        $this->write('rex_media_large/bar.png/hashB');

        $deleted = MediaManager::deleteCache();

        self::assertSame(2, $deleted);
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_small');
        self::assertDirectoryDoesNotExist($this->dir . '/rex_media_large');
    }

    private function write(string $relative): void
    {
        $path = $this->dir . '/' . $relative;
        Dir::create(dirname($path));
        file_put_contents($path, 'x');
    }
}
