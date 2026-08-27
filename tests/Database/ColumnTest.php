<?php

namespace Redaxo\Core\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Exception\InvalidArgumentException;

/** @internal */
final class ColumnTest extends TestCase
{
    /** @param array{string, bool, string|null, string|null, string|null} $expected */
    #[DataProvider('provideFactories')]
    public function testFactory(array $expected, Column $column): void
    {
        self::assertSame($expected, [
            $column->getType(),
            $column->isNullable(),
            $column->getDefault(),
            $column->getExtra(),
            $column->getComment(),
        ]);

        // A factory must produce the same spelling that reading the column back from the database yields.
        self::assertSame($column->getDefault(), Column::normalizeDefault($column->getType(), $column->getDefault()));
        self::assertSame($column->getExtra(), Column::normalizeExtra($column->getExtra()));
    }

    /** @return iterable<string, array{array{string, bool, string|null, string|null, string|null}, Column}> */
    public static function provideFactories(): iterable
    {
        yield 'int' => [['int(11)', false, null, null, null], Column::int('a')];
        yield 'int unsigned' => [['int(10) unsigned', false, null, null, null], Column::int('a', unsigned: true)];
        yield 'int auto increment' => [['int(10) unsigned', false, null, 'auto_increment', null], Column::int('a', unsigned: true, autoIncrement: true)];
        yield 'int with default' => [['int(11)', true, '-5', null, 'c'], Column::int('a', nullable: true, default: -5, comment: 'c')];
        yield 'tinyint' => [['tinyint(4)', false, '0', null, null], Column::tinyint('a', default: 0)];
        yield 'smallint unsigned' => [['smallint(5) unsigned', false, null, null, null], Column::smallint('a', unsigned: true)];
        yield 'mediumint' => [['mediumint(9)', false, null, null, null], Column::mediumint('a')];
        yield 'bigint unsigned' => [['bigint(20) unsigned', false, null, null, null], Column::bigint('a', unsigned: true)];

        yield 'bool' => [['tinyint(1)', false, null, null, null], Column::bool('a')];
        yield 'bool true' => [['tinyint(1)', false, '1', null, null], Column::bool('a', default: true)];
        yield 'bool false' => [['tinyint(1)', false, '0', null, null], Column::bool('a', default: false)];

        yield 'varchar' => [['varchar(191)', false, null, null, null], Column::varchar('a', 191)];
        yield 'text' => [['text', true, null, null, null], Column::text('a', nullable: true)];
        yield 'mediumtext' => [['mediumtext', false, null, null, null], Column::mediumtext('a')];
        yield 'longtext' => [['longtext', false, null, null, null], Column::longtext('a')];

        yield 'decimal' => [['decimal(10,2)', false, null, null, null], Column::decimal('a', 10, 2)];
        yield 'decimal unsigned' => [['decimal(10,2) unsigned', false, '0.00', null, null], Column::decimal('a', 10, 2, unsigned: true, default: '0.00')];

        yield 'date' => [['date', false, null, null, null], Column::date('a')];
        yield 'time' => [['time', false, null, null, null], Column::time('a')];
        yield 'time with precision' => [['time(3)', false, null, null, null], Column::time('a', 3)];

        yield 'datetime' => [['datetime', false, null, null, null], Column::datetime('a')];
        yield 'datetime with default' => [['datetime', false, '2026-01-01 00:00:00', null, null], Column::datetime('a', default: '2026-01-01 00:00:00')];
        yield 'datetime with precision' => [['datetime(3)', true, null, null, null], Column::datetime('a', 3, nullable: true)];
    }

    /**
     * The factories validate at runtime as well, for values that static analysis cannot check.
     *
     * @param list<mixed> $arguments
     */
    #[DataProvider('provideInvalidFactoryArguments')]
    public function testFactoryRejectsInvalidArguments(string $method, array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        Column::$method(...$arguments);
    }

    /** @return iterable<string, array{string, list<mixed>}> */
    public static function provideInvalidFactoryArguments(): iterable
    {
        yield 'varchar length zero' => ['varchar', ['a', 0]];
        yield 'varchar length too large' => ['varchar', ['a', 65536]];
        yield 'decimal precision zero' => ['decimal', ['a', 0, 0]];
        yield 'decimal precision too large' => ['decimal', ['a', 66, 2]];
        yield 'decimal scale above precision' => ['decimal', ['a', 2, 3]];
        yield 'decimal scale too large' => ['decimal', ['a', 65, 31]];
        yield 'datetime precision too large' => ['datetime', ['a', 7]];
        yield 'time precision negative' => ['time', ['a', -1]];
    }

    #[DataProvider('provideDefaults')]
    public function testNormalizeDefault(?string $expected, string $type, ?string $default): void
    {
        self::assertSame($expected, Column::normalizeDefault($type, $default));
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
        self::assertSame($expected, Column::normalizeExtra($extra));
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
