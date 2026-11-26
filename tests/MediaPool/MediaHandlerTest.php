<?php

namespace Redaxo\Core\Tests\MediaPool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Fixtures\TestMediaMetaSchema;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaAdding;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaListQuery;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaUpdated;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPool;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Type;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_slice;
use function count;

use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_PARTIAL;

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
            self::fail('Expected a UserMessageException');
        } catch (UserMessageException) {
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

    public function testAddMediaClearsInstancePool(): void
    {
        $srcFile = self::getPath('media_handler_test.txt');
        File::put($srcFile, 'test');

        $filename = MediaPool::filename('media_handler_test.txt');

        // populate the instance pools, including a cached null for the not yet existing media
        $media = Media::get($filename);
        self::assertNull($media);
        $rootMedia = Media::getRootMedia();

        $addedMedia = MediaHandler::add([
            'category_id' => null,
            'title' => 'test',
            'file' => new SymfonyFile($srcFile),
        ], subindexing: false);

        try {
            self::assertSame($filename, $addedMedia->fileName);
            self::assertSame($filename, Media::get($filename)?->fileName);

            $rootMediaNames = array_map(static fn (Media $media) => $media->fileName, Media::getRootMedia());
            self::assertCount(count($rootMedia) + 1, $rootMediaNames);
            self::assertContains($filename, $rootMediaNames);
        } finally {
            MediaHandler::delete($filename);
        }
    }

    public function testAddMediaCanBeRejected(): void
    {
        $srcFile = self::getPath('media_handler_test.txt');
        File::put($srcFile, 'test');

        $filename = MediaPool::filename('media_handler_test.txt');

        $extensions = Type::array(new ReflectionProperty(Extension::class, 'extensions')->getValue());

        try {
            Extension::register(MediaAdding::class, static function (MediaAdding $ep) use ($filename, $srcFile): string {
                self::assertSame($filename, $ep->fileName);
                self::assertSame($srcFile, $ep->path);
                self::assertFalse($ep->isUpload);

                return 'rejected';
            });

            MediaHandler::add([
                'category_id' => null,
                'title' => 'test',
                'file' => new SymfonyFile($srcFile),
            ], subindexing: false);

            self::fail('Expected a UserMessageException');
        } catch (UserMessageException $e) {
            self::assertSame('rejected', $e->getMessage());
            self::assertNull(Media::get($filename));
            self::assertFileDoesNotExist(Path::media($filename));
        } finally {
            new ReflectionProperty(Extension::class, 'extensions')->setValue(null, $extensions);
        }
    }

    public function testMediaListQueryCanBeExtended(): void
    {
        $filenames = [];
        foreach (['a', 'b'] as $suffix) {
            $srcFile = self::getPath('media_list_query_' . $suffix . '.txt');
            File::put($srcFile, 'test');
            $filenames[] = MediaHandler::add([
                'category_id' => null,
                'title' => 'test',
                'file' => new SymfonyFile($srcFile),
            ], subindexing: false)->fileName;
        }

        $extensions = Type::array(new ReflectionProperty(Extension::class, 'extensions')->getValue());

        try {
            $counted = [];
            Extension::register(MediaListQuery::class, static function (MediaListQuery $ep) use ($filenames, &$counted): string {
                $counted[] = $ep->count;
                $ep->params['only'] = $filenames[0];

                // the data query already ends with ORDER BY and LIMIT, so the condition is added to the WHERE clause
                return str_replace(' WHERE ', ' WHERE m.filename = :only AND ', $ep->subject);
            });

            $pager = new Pager(10);
            $items = MediaHandler::getList(['term' => 'media_list_query'], pager: $pager);

            self::assertSame([true, false], $counted);
            self::assertSame(1, $pager->getRowCount());
            self::assertSame([$filenames[0]], array_map(static fn (Media $media) => $media->fileName, $items));
        } finally {
            new ReflectionProperty(Extension::class, 'extensions')->setValue(null, $extensions);
            foreach ($filenames as $filename) {
                MediaHandler::delete($filename);
            }
        }
    }

    public function testUpdateMediaDispatchesPreviousState(): void
    {
        $srcFile = self::getPath('media_handler_test.txt');
        File::put($srcFile, 'test');

        $filename = MediaHandler::add([
            'category_id' => null,
            'title' => 'old title',
            'file' => new SymfonyFile($srcFile),
        ], subindexing: false)->fileName;

        $extensions = Type::array(new ReflectionProperty(Extension::class, 'extensions')->getValue());

        try {
            $events = [];
            Extension::register(MediaUpdated::class, static function (MediaUpdated $ep) use (&$events): void {
                $events[] = $ep;
            });

            $media = MediaHandler::edit($filename, 1, ['title' => 'new title']);

            self::assertSame('new title', $media->title);
            self::assertCount(1, $events);
            self::assertSame($media, $events[0]->media);
            self::assertSame('old title', $events[0]->previous->title);
        } finally {
            new ReflectionProperty(Extension::class, 'extensions')->setValue(null, $extensions);
            MediaHandler::delete($filename);
        }
    }

    public function testSaveMetaValues(): void
    {
        $srcFile = self::getPath('media_handler_test.txt');
        File::put($srcFile, 'test');

        $filename = MediaHandler::add([
            'category_id' => null,
            'title' => 'test',
            'file' => new SymfonyFile($srcFile),
        ], ['ai_generated' => true, 'alt_text' => 'old alt'], subindexing: false)->fileName;

        try {
            $meta = TestMediaMetaSchema::of(Type::notNull(Media::get($filename)));
            self::assertTrue($meta->aiGenerated);
            self::assertSame('old alt', $meta->altText);

            // values missing in the array are kept
            $media = MediaHandler::edit($filename, 1, [], ['alt_text' => 'new alt']);

            $meta = TestMediaMetaSchema::of($media);
            self::assertTrue($meta->aiGenerated);
            self::assertSame('new alt', $meta->altText);
        } finally {
            MediaHandler::delete($filename);
        }
    }

    public function testAddUpload(): void
    {
        $srcFile = self::getPath('phpUpl04d');
        File::put($srcFile, 'test');

        $isUpload = null;
        $extensions = Type::array(new ReflectionProperty(Extension::class, 'extensions')->getValue());
        Extension::register(MediaAdding::class, static function (MediaAdding $ep) use (&$isUpload): void {
            $isUpload = $ep->isUpload;
        });

        try {
            $media = MediaHandler::add([
                'file' => new UploadedFile($srcFile, 'media_handler_test.txt', test: true),
                'category_id' => null,
            ], subindexing: false);
        } finally {
            new ReflectionProperty(Extension::class, 'extensions')->setValue(null, $extensions);
        }

        try {
            self::assertTrue($isUpload);
            self::assertSame('media_handler_test.txt', $media->fileName);
            self::assertSame('', $media->title);
            self::assertFileDoesNotExist($srcFile);
        } finally {
            MediaHandler::delete($media->fileName);
        }
    }

    #[DataProvider('provideAddRejectsFailedUpload')]
    public function testAddRejectsFailedUpload(string $expectedMessage, int $error): void
    {
        $this->expectException(UserMessageException::class);
        $this->expectExceptionMessage($expectedMessage);

        MediaHandler::add([
            'file' => new UploadedFile(self::getPath('phpUpl04d'), 'media_handler_test.txt', error: $error, test: true),
            'category_id' => null,
        ]);
    }

    /** @return iterable<int, array{string, int}> */
    public static function provideAddRejectsFailedUpload(): iterable
    {
        yield [I18n::msg('pool_file_upload_error_size', Formatter::bytes(Request::getMaxUploadSize())), UPLOAD_ERR_INI_SIZE];
        yield [I18n::msg('pool_file_not_found'), UPLOAD_ERR_NO_FILE];
        yield [I18n::msg('pool_file_upload_error'), UPLOAD_ERR_PARTIAL];
    }

    public function testAddRejectsNonUploadedFile(): void
    {
        $srcFile = self::getPath('phpUpl04d');
        File::put($srcFile, 'test');

        $this->expectException(UserMessageException::class);
        $this->expectExceptionMessage(I18n::msg('pool_file_upload_error'));

        // without test mode the file must have been uploaded via http
        MediaHandler::add(['file' => new UploadedFile($srcFile, 'media_handler_test.txt'), 'category_id' => null]);
    }

    public function testEditReplacesFile(): void
    {
        $srcFile = self::getPath('media_handler_test.txt');
        File::put($srcFile, 'old');

        $filename = MediaHandler::add([
            'file' => new SymfonyFile($srcFile),
            'category_id' => null,
            'title' => 'title',
        ], subindexing: false)->fileName;

        try {
            $newFile = self::getPath('replacement.txt');
            File::put($newFile, 'new content');

            $media = MediaHandler::edit($filename, 1, ['file' => new SymfonyFile($newFile)]);

            self::assertSame('new content', File::get(Path::media($filename)));
            self::assertSame('replacement.txt', $media->originalFileName);
            self::assertSame('title', $media->title);

            $pdf = self::getPath('replacement.pdf');
            File::put($pdf, 'pdf');

            $this->expectException(UserMessageException::class);
            MediaHandler::edit($filename, 1, ['file' => new SymfonyFile($pdf)]);
        } finally {
            MediaHandler::delete($filename);
        }
    }

    /**
     * @param list<array{string, 'ASC'|'DESC'}> $orderBy
     * @param list<string> $expected
     */
    #[DataProvider('provideGetListOrder')]
    public function testGetListOrder(array $orderBy, array $expected): void
    {
        // inserted in an order that matches none of the expected orders
        $media = [
            ['getlistorder-b.txt', 'Alpha', '2026-01-02 00:00:00'],
            ['getlistorder-c.txt', 'Charlie', '2026-01-01 00:00:00'],
            ['getlistorder-a.txt', 'Bravo', '2026-01-03 00:00:00'],
        ];
        $filenames = [];

        try {
            foreach ($media as [$filename, $title, $updatedate]) {
                $srcFile = self::getPath($filename);
                File::put($srcFile, 'test');
                $filenames[] = $filename = MediaHandler::add([
                    'category_id' => null,
                    'title' => $title,
                    'file' => new SymfonyFile($srcFile),
                ], subindexing: false)->fileName;

                Sql::factory()->setQuery('UPDATE rex_media SET updatedate = ? WHERE filename = ?', [$updatedate, $filename]);
            }

            $getFilenames = static fn (array $list) => array_map(static fn (Media $media) => $media->fileName, $list);

            $list = MediaHandler::getList(['term' => 'getlistorder'], $orderBy);
            self::assertSame($expected, $getFilenames($list));

            $pager = new Pager(2);
            $pager->setCursor(0);
            $list = MediaHandler::getList(['term' => 'getlistorder'], $orderBy, $pager);
            self::assertSame(3, $pager->getRowCount());
            self::assertSame(array_slice($expected, 0, 2), $getFilenames($list));
        } finally {
            foreach ($filenames as $filename) {
                MediaHandler::delete($filename);
            }
        }
    }

    /** @return iterable<string, array{list<array{string, 'ASC'|'DESC'}>, list<string>}> */
    public static function provideGetListOrder(): iterable
    {
        $a = 'getlistorder-a.txt';
        $b = 'getlistorder-b.txt';
        $c = 'getlistorder-c.txt';

        yield 'filename asc' => [[['filename', 'ASC']], [$a, $b, $c]];
        yield 'filename desc' => [[['filename', 'DESC']], [$c, $b, $a]];
        yield 'title asc' => [[['title', 'ASC']], [$b, $a, $c]];
        yield 'updatedate asc' => [[['updatedate', 'ASC']], [$c, $b, $a]];
        yield 'default: updatedate desc' => [[], [$a, $b, $c]];
    }
}
