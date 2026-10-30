<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_media_service_test extends TestCase
{
    private const EVIL_SVG = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="1" onclick="alert(2)"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        rex_dir::create(self::getPath());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        rex_dir::delete(self::getPath());
        rex_dir::delete(self::getTempPath());
    }

    private static function getPath(string $file = ''): string
    {
        return rex_path::addonData('tests', 'rex_media_service_test/' . $file);
    }

    private static function getTempPath(): string
    {
        return rex_path::addonCache('mediapool', 'sanitize');
    }

    private static function moveMedia(string $srcFile, string $dstFile, ?string $type): void
    {
        (new ReflectionMethod(rex_media_service::class, 'moveMedia'))->invoke(null, $srcFile, $dstFile, $type);
    }

    private static function assertSanitized(string $file): void
    {
        $content = rex_file::get($file);

        self::assertIsString($content);
        self::assertStringNotContainsString('<script', $content);
        self::assertStringNotContainsString('onclick', $content);
        self::assertStringContainsString('<circle', $content);
    }

    public function testMoveSanitizesSvg(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        rex_file::put($srcFile, self::EVIL_SVG);

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
        rex_file::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, null);

        self::assertSanitized($dstFile);
    }

    public function testMoveSanitizesSvgInPlace(): void
    {
        $file = self::getPath('inplace.svg');
        rex_file::put($file, self::EVIL_SVG);

        self::moveMedia($file, $file, 'image/svg+xml');

        self::assertSanitized($file);
    }

    public function testMoveRejectsUnparsableSvg(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        rex_file::put($srcFile, '<svg><unclosed &&&');

        try {
            self::moveMedia($srcFile, $dstFile, 'image/svg+xml');
            self::fail('Expected a rex_api_exception');
        } catch (rex_api_exception) {
            // expected
        }

        self::assertFileDoesNotExist($dstFile);
    }

    public function testMoveKeepsOtherFilesUntouched(): void
    {
        $srcFile = self::getPath('source.txt');
        $dstFile = self::getPath('target.txt');
        rex_file::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, 'text/plain');

        self::assertSame(self::EVIL_SVG, rex_file::get($dstFile));
        self::assertFileDoesNotExist($srcFile);
    }

    public function testMoveRemovesTempFile(): void
    {
        $srcFile = self::getPath('source.svg');
        $dstFile = self::getPath('target.svg');
        rex_file::put($srcFile, self::EVIL_SVG);

        self::moveMedia($srcFile, $dstFile, 'image/svg+xml');

        self::assertSame([], glob(self::getTempPath() . '/*') ?: []);
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
        foreach ($media as [$filename, $title, $updatedate]) {
            rex_sql::factory()
                ->setTable(rex::getTable('media'))
                ->setValues([
                    'filename' => $filename,
                    'originalname' => $filename,
                    'filetype' => 'text/plain',
                    'filesize' => 1,
                    'title' => $title,
                    'category_id' => 0,
                    'createdate' => $updatedate,
                    'updatedate' => $updatedate,
                    'createuser' => 'test',
                    'updateuser' => 'test',
                ])
                ->insert();
        }

        try {
            $list = rex_media_service::getList(['term' => 'getlistorder'], $orderBy);

            self::assertSame($expected, array_map(static fn (rex_media $media) => $media->getFileName(), $list));
        } finally {
            rex_sql::factory()->setQuery(
                'DELETE FROM ' . rex::getTable('media') . ' WHERE filename LIKE ?',
                ['getlistorder-%'],
            );
            foreach ($media as [$filename]) {
                rex_media_cache::delete($filename);
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
