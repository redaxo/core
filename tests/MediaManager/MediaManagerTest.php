<?php

namespace Redaxo\Core\Tests\MediaManager;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaManager\ManagedMedia;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use ReflectionProperty;

/** @internal */
final class MediaManagerTest extends TestCase
{
    public function testGetCacheFilename(): void
    {
        $media = new ManagedMedia(__DIR__ . '/foo.jpg');
        $manager = new MediaManager($media);

        $cachePath = Path::coreCache('media_manager/');
        $manager->setCachePath($cachePath);

        $media->setMediaPath(__DIR__ . '/bar.gif');

        $property = new ReflectionProperty(MediaManager::class, 'type');
        $property->setValue($manager, 'test');

        self::assertSame($cachePath . 'test/foo.jpg', $manager->getCacheFilename());
    }

    public function testGetMediaFile(): void
    {
        $_GET['rex_media_file'] = '../foo/bar/baz.jpg';
        self::assertSame('baz.jpg', MediaManager::getMediaFile());

        $_GET['rex_media_file'] = '..\\foo\\bar\\baz.jpg';
        self::assertSame('baz.jpg', MediaManager::getMediaFile());
    }

    public function testCreate(): void
    {
        $filename = '_media_manager_test.png';
        $path = Path::media($filename);

        File::put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII='));

        try {
            $manager = MediaManager::create('rex_media_small', $filename);

            self::assertFileExists($manager->getCacheFilename());
            self::assertFileExists($manager->getHeaderCacheFilename());

            $manager = MediaManager::create('non_existing_type', $filename);

            self::assertFileDoesNotExist($manager->getCacheFilename());
            self::assertFileDoesNotExist($manager->getHeaderCacheFilename());
        } finally {
            @unlink($path);
        }
    }

    #[DataProvider('dataGetUrl')]
    public function testGetUrl(int|false $expectedBuster, string $type, string|Media $file, ?int $timestamp = null): void
    {
        $url = MediaManager::getUrl($type, $file, $timestamp);

        if (false === $expectedBuster) {
            self::assertStringNotContainsString('buster=', $url);
        } else {
            self::assertStringContainsString('buster=' . $expectedBuster, $url);
        }
    }

    /** @return iterable<int, array{0: (false|int), 1: string, 2: (string|Media), 3?: int}> */
    public static function dataGetUrl(): iterable
    {
        yield [false, 'non_existing', 'test.jpg', time()];

        $media = new class extends Media {
            public int $fakeUpdateDate = 0;

            public function __construct() {}

            public function getFileName(): string
            {
                return 'test.jpg';
            }

            public function getUpdateDate(): int
            {
                return $this->fakeUpdateDate;
            }
        };
        $media->fakeUpdateDate = time();

        yield [false, 'non_existing', $media];

        $type = 'rex_media_small';

        yield [false, $type, 'test.jpg'];

        $typeTimestamp = (int) Sql::factory()
            ->setQuery('SELECT updatedate FROM ' . Core::getTable('media_manager_type') . ' WHERE name = ?', [$type])
            ->getDateTimeValue('updatedate');

        foreach ([$typeTimestamp - 1000, $typeTimestamp + 1000] as $fileTimestamp) {
            $expectedBuster = max($typeTimestamp, $fileTimestamp);

            yield [$expectedBuster, $type, 'test.jpg', $fileTimestamp];

            $media = clone $media;
            $media->fakeUpdateDate = $fileTimestamp;

            yield [$expectedBuster, $type, $media];
        }
    }
}
