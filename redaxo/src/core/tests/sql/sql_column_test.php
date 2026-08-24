<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_sql_column_test extends TestCase
{
    #[DataProvider('provideDefaults')]
    public function testNormalizeDefault(?string $expected, string $type, ?string $default): void
    {
        self::assertSame($expected, rex_sql_column::normalizeDefault($type, $default));
    }

    /** @return iterable<string, array{string|null, string, string|null}> */
    public static function provideDefaults(): iterable
    {
        yield 'null' => [null, 'datetime', null];
        yield 'literal value' => ['2026-01-01 00:00:00', 'datetime', '2026-01-01 00:00:00'];
        yield 'mysql spelling' => ['CURRENT_TIMESTAMP', 'datetime', 'CURRENT_TIMESTAMP'];
        yield 'mariadb spelling' => ['CURRENT_TIMESTAMP', 'datetime', 'current_timestamp()'];
        yield 'timestamp' => ['CURRENT_TIMESTAMP', 'timestamp', 'current_timestamp()'];
        yield 'with precision' => ['CURRENT_TIMESTAMP(3)', 'datetime(3)', 'current_timestamp(3)'];
        yield 'non temporal type' => ['current_timestamp()', 'varchar(255)', 'current_timestamp()'];
    }

    #[DataProvider('provideExtras')]
    public function testNormalizeExtra(?string $expected, ?string $extra): void
    {
        self::assertSame($expected, rex_sql_column::normalizeExtra($extra));
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function provideExtras(): iterable
    {
        yield 'null' => [null, null];
        yield 'auto increment' => ['auto_increment', 'auto_increment'];
        yield 'mysql spelling' => ['on update CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'];
        yield 'mariadb spelling' => ['on update CURRENT_TIMESTAMP', 'on update current_timestamp()'];
        yield 'with precision' => ['on update CURRENT_TIMESTAMP(3)', 'on update current_timestamp(3)'];
        yield 'generated default' => [null, 'DEFAULT_GENERATED'];
        yield 'generated default on update' => ['on update CURRENT_TIMESTAMP', 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'];
    }
}
