<?php

namespace Redaxo\Core\Tests\MediaPool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\MediaPool\MediaPool;

/** @internal */
final class MediaPoolTest extends TestCase
{
    #[DataProvider('provideIsAllowedExtension')]
    public function testIsAllowedExtension(bool $expected, string $filename, array $args = []): void
    {
        self::assertSame($expected, MediaPool::isAllowedExtension($filename, $args));
    }

    /** @return list<array{0: bool, 1: string, 2?: array{types: string}}> */
    public static function provideIsAllowedExtension(): array
    {
        return [
            [false, 'foo.bar.php'],
            [false, 'foo.bar.php5'],
            [false, 'foo.bar.php71'],
            [false, 'foo.bar.php_71'],
            [false, 'foo.bar.jsp'],
            [false, '.htaccess'],
            [false, '.htpasswd'],
            [false, 'foo.js.txt'],
            [true, 'js_datei.txt'],
            [true, 'foo.json'],
            [true, 'php_logo.jpg'],
            [true, 'foo.bar.png', ['types' => 'jpg,png,gif']],
            [false, 'foo.bar.txt', ['types' => 'jpg,png,gif']],
            [false, 'foo.bar.php', ['types' => 'jpg,png,gif,php']],
        ];
    }

    #[DataProvider('provideIsAllowedMimeType')]
    public function testIsAllowedMimeType(bool $expected, string $path, ?string $filename = null): void
    {
        $allowedMimeTypes = MediaPool::getAllowedMimeTypes();

        MediaPool::setAllowedMimeTypes([
            'md' => ['text/plain'],
        ]);

        self::assertSame($expected, MediaPool::isAllowedMimeType($path, $filename));

        MediaPool::setAllowedMimeTypes($allowedMimeTypes);
    }

    /** @return list<array{0: bool, 1: string, 2?: string}> */
    public static function provideIsAllowedMimeType(): array
    {
        return [
            [false, __FILE__],
            [false, __FILE__, 'foo.md'],
            [true, __DIR__ . '/../../SECURITY.md'],
            [false, __DIR__ . '/../../SECURITY.md', 'foo.txt'],
        ];
    }
}
