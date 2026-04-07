<?php

namespace Redaxo\Core\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Util\Version;

/** @internal */
final class VersionTest extends TestCase
{
    public function testIsUnstable(): void
    {
        self::assertTrue(Version::isUnstable('1.0-dev'));
        self::assertTrue(Version::isUnstable('2.1.0beta'));
        self::assertTrue(Version::isUnstable('1.0b1'));
        self::assertTrue(Version::isUnstable('3.5.1-alpha'));
        self::assertTrue(Version::isUnstable('99.99RC5'));
        self::assertTrue(Version::isUnstable('199.199 RC3'));

        self::assertFalse(Version::isUnstable('1.0'));
        self::assertFalse(Version::isUnstable('1.0-final'));
        self::assertFalse(Version::isUnstable('2.45 stable'));
        self::assertFalse(Version::isUnstable('1.0 codename starship'));
    }

    /** @return list<array{string, list<string>}> */
    public static function splitProvider(): array
    {
        return [
            ['1.1.2',      ['1', '1', '2']],
            ['1.2alpha1',  ['1', '2', 'alpha', '1']],
            ['1_2 beta 2', ['1', '2', 'beta', '2']],
            ['2.2.3-dev',  ['2', '2', '3', 'dev']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('splitProvider')]
    public function testSplit(string $version, array $expected): void
    {
        self::assertEquals($expected, Version::split($version));
    }

    /** @return list<array{bool, string, string, '='|'=='|'!='|'<>'|'<'|'<='|'>'|'>='|null}> */
    public static function compareProvider(): array
    {
        return [
            [true, '1',      '1',      '='],
            [true, '1.0',    '1.0',    '='],
            [true, '1',      '1.0',    '='],
            [true, '1.0 a1', '1.0.a1', '='],
            [true, '1.0a1',  '1.0.a1', '='],
            [true, '1.0 alpha 1', '1.0.a1', '='],

            [true, '1',      '2',        '<'],
            [true, '1',      '1.1',      '<'],
            [true, '1.0',    '1.1',      '<'],
            [true, '1.1',    '1.2',      '<'],
            [true, '1.2',    '1.10',     '<'],
            [true, '1.a1',   '1',        '<'],
            [true, '1.a1',   '1.0',      '<'],
            [true, '1.a1',   '1.a2',     '<'],
            [true, '1.a1',   '1.b1',     '<'],
            [true, '1.0.a1', '1',        '<'],
            [true, '1.0.a1', '1.0.0.0.', '<'],
            [true, '1.0a1',  '1.0',      '<'],
            [true, '1.0a1',  '1.0.1',    '<'],
            [true, '1.0a1',  '1.0a2',    '<'],
            [true, '1.0',    '1.1a1',    '<'],
            [true, '1.0.1',  '1.1a1',    '<'],

            [false, '1.0', '1.0', null],
            [true, '1.0', '1.1', null],
            [false, '1.1', '1.0', null],
        ];
    }

    /** @param '='|'=='|'!='|'<>'|'<'|'<='|'>'|'>='|null $comparator */
    #[DataProvider('compareProvider')]
    public function testCompare(bool $expected, string $version1, string $version2, ?string $comparator): void
    {
        self::assertSame($expected, Version::compare($version1, $version2, $comparator));
    }

    public function testGitHash(): void
    {
        self::assertIsString(Version::gitHash(__DIR__));

        self::assertNull(Version::gitHash(__DIR__, 'foo/bar'));
    }
}
