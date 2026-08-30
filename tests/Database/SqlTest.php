<?php

namespace Redaxo\Core\Tests\Database;

use DateTime;
use DateTimeZone;
use Override;
use PDO;
use Pdo\Mysql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Util\Type;

/** @internal */
final class SqlTest extends TestCase
{
    public const string TABLE = 'rex_tests_table';
    public const string VIEW = 'rex_tests_view';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $sql = Sql::factory();

        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $sql->setQuery('CREATE TABLE `' . self::TABLE . '` (
            `id` INT NOT NULL AUTO_INCREMENT ,
            `col_str` VARCHAR( 255 ) NULL ,
            `col_int` INT NULL ,
            `col_date` DATE NULL ,
            `col_time` DATETIME NULL ,
            `col_text` TEXT NULL ,
            PRIMARY KEY ( `id` )
        ) ENGINE = InnoDB ;');

        $sql->setQuery('DROP VIEW IF EXISTS `' . self::VIEW . '`');
        $sql->setQuery('CREATE VIEW `' . self::VIEW . '` AS SELECT * FROM `' . self::TABLE . '`');
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE `' . self::TABLE . '`');
        $sql->setQuery('DROP VIEW `' . self::VIEW . '`');
    }

    public function testFactory(): void
    {
        $sql = Sql::factory();
        self::assertInstanceOf(Sql::class, $sql); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public function testCheckConnection(): void
    {
        $dbConfig = Core::getDbConfig();
        self::assertTrue(Sql::checkDbConnection($dbConfig->host, $dbConfig->login, $dbConfig->password, $dbConfig->name));
    }

    public function testCheckConnectionInvalidPassword(): void
    {
        $dbConfig = Core::getDbConfig();
        self::assertTrue(true !== Sql::checkDbConnection($dbConfig->host, $dbConfig->login, 'fu-password', $dbConfig->name));
    }

    public function testCheckConnectionInvalidHost(): void
    {
        $dbConfig = Core::getDbConfig();
        self::assertTrue(true !== Sql::checkDbConnection('fu-host', $dbConfig->login, $dbConfig->password, $dbConfig->name));
    }

    public function testCheckConnectionInvalidLogin(): void
    {
        $dbConfig = Core::getDbConfig();
        self::assertTrue(true !== Sql::checkDbConnection($dbConfig->host, 'fu-login', $dbConfig->password, $dbConfig->name));
    }

    public function testCheckConnectionInvalidDatabase(): void
    {
        $dbConfig = Core::getDbConfig();
        self::assertTrue(true !== Sql::checkDbConnection($dbConfig->host, $dbConfig->login, $dbConfig->password, 'fu-database'));
    }

    public function testSessionTimeZoneFollowsPhp(): void
    {
        $timeZone = Sql::factory()->getArray('SELECT @@session.time_zone AS tz')[0]['tz'];

        // The named zone is used when the database knows it, otherwise the current offset.
        self::assertContains($timeZone, [date_default_timezone_get(), date('P')]);
    }

    /** Without a matching session time zone the database clock is off by the difference to the server timezone. */
    public function testCurrentTimeMatchesPhp(): void
    {
        $now = Sql::factory()->getArray('SELECT NOW() AS now')[0]['now'];

        self::assertIsString($now);
        self::assertLessThanOrEqual(5, abs(Type::int(strtotime($now)) - time()));
    }

    #[DataProvider('provideDbType')]
    public function testDbType(string $expected, string $version): void
    {
        $sql = $this->getVersionMock($version);

        self::assertSame($expected, $sql->getDbType());
    }

    /** @return list<array{string, string}> */
    public static function provideDbType(): array
    {
        return [
            [Sql::MYSQL, '5.7.7'],
            [Sql::MYSQL, '5.6.19-67.0-log'],
            [Sql::MARIADB, '10.2.0-mariadb'],
            [Sql::MARIADB, '5.5.5-10.4.10-MariaDB'],
            [Sql::MARIADB, '5.5.5-10.3.18-MariaDB-log'],
        ];
    }

    #[DataProvider('provideDbVersion')]
    public function testDbVersion(string $expected, string $version): void
    {
        $sql = $this->getVersionMock($version);

        self::assertSame($expected, $sql->getDbVersion());
    }

    /** @return list<array{string, string}> */
    public static function provideDbVersion(): array
    {
        return [
            ['5.7.7', '5.7.7'],
            ['5.6.19', '5.6.19-67.0-log'],
            ['10.2.0', '10.2.0-mariadb'],
            ['10.4.10', '5.5.5-10.4.10-MariaDB'],
            ['10.3.18', '5.5.5-10.3.18-MariaDB-log'],
            ['unexpected-1.0-foo', 'unexpected-1.0-foo'],
        ];
    }

    private function getVersionMock(string $version): Sql
    {
        return new class(version: $version) extends Sql {
            public function __construct(int $db = 999, ?string $version = null)
            {
                parent::__construct($db);

                self::$pdo[$db] = new class(Type::notNull($version)) extends Mysql {
                    public function __construct(
                        private readonly string $version,
                    ) {}

                    public function getAttribute(int $attribute): string
                    {
                        return $this->version;
                    }
                };
            }

            public function __destruct()
            {
                unset(self::$pdo[$this->DBID]);
            }
        };
    }

    public function testEscapeLikeWildcards(): void
    {
        $sql = Sql::factory();

        self::assertSame('\\%foo\\_bar\\\\baz\\\\\\_qux', $sql->escapeLikeWildcards('%foo_bar\\baz\\_qux'));
    }

    /** @param list<int|string> $values */
    #[DataProvider('dataIn')]
    public function testIn(string $expected, array $values): void
    {
        $sql = Sql::factory();
        $in = $sql->in($values);

        self::assertSame($expected, $in);
    }

    /** @return list<array{string, list<int|string>}> */
    public static function dataIn(): iterable
    {
        return [
            ['', []],
            ['3', [3]],
            ["'foo'", ['foo']],
            ['3, 13, 6', [3, 13, 6]],
            ["'3', 'foo', '14', 'bar', ''", [3, 'foo', 14, 'bar', '']],
        ];
    }

    public function testSetGetValue(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_str', 'abc');
        $sql->setValue('col_int', 5);

        self::assertTrue($sql->hasValue('col_str'), 'set value string exists');
        self::assertTrue($sql->hasValue('col_int'), 'set value int exists');

        self::assertEquals('abc', $sql->getValue('col_str'), 'get a previous set string');
        self::assertEquals(5, $sql->getValue('col_int'), 'get a previous set int ');
    }

    public function testSetGetArrayValue(): void
    {
        $sql = Sql::factory();
        $sql->setArrayValue('col_empty_array', []);
        $sql->setArrayValue('col_array', [1, 2, 3]);

        self::assertTrue($sql->hasValue('col_empty_array'), 'set value exists');
        self::assertTrue($sql->hasValue('col_array'), 'set value exists');

        self::assertEquals([], $sql->getArrayValue('col_empty_array'), 'get a previous set empty array');
        self::assertEquals([1, 2, 3], $sql->getArrayValue('col_array'), 'get a previous set array');
    }

    public function testNullInSetGetArrayValue(): void
    {
        $sql = Sql::factory();
        $sql->setValue('col_array', null);
        self::assertEquals([], $sql->getArrayValue('col_array'), 'get a previous set array');
    }

    public function testInvalidJsonInSetGetArrayValue(): void
    {
        $sql = Sql::factory();
        $sql->setValue('col_array', 'not-a valid json string');

        self::assertTrue($sql->hasValue('col_array'), 'set value exists');

        self::expectException(SqlException::class);
        $sql->getArrayValue('col_array');
    }

    public function testInsertRow(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_int', 5);
        $sql->setValue('col_str', 'abc');
        $sql->setValue('col_text', 'mytext');

        $sql->insert();
        self::assertEquals(1, $sql->getRows());
        // failing at the moment
        // $this->assertEquals('abc', $sql->getValue('col_str'));
        // $this->assertEquals(5, $sql->getValue('col_int'));
    }

    public function testInsertRawValue(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setRawValue('col_time', 'NOW()');

        $sql->insert();
        self::assertEquals(1, $sql->getRows());
    }

    public function testInsertWithoutValues(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->insert();
        self::assertEquals(1, $sql->getRows());
    }

    public function testInsertRecords(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->addRecord(static function (Sql $record) {
            $record->setValue('col_str', 'foo');
            $record->setRawValue('col_date', 'UTC_DATE()');
            $record->setValue('col_int', 3);
        });
        $sql->addRecord(static function (Sql $record) {
            $record->setValue('col_str', 'bar');
            $record->setDateTimeValue('col_date', strtotime('yesterday'));
            $record->setValue('col_text', 'lorem ipsum');
        });

        $sql->insert();

        self::assertSame(2, $sql->getRows());

        $sql->setTable(self::TABLE)->select();

        self::assertSame(2, $sql->getRows());

        self::assertSame('foo', $sql->getValue('col_str'));
        self::assertSame(new DateTime('now', new DateTimeZone('UTC'))->format('Y-m-d'), $sql->getValue('col_date'));
        self::assertEquals(3, $sql->getValue('col_int'));

        $sql->next();

        self::assertSame('bar', $sql->getValue('col_str'));
        self::assertSame(date('Y-m-d', strtotime('yesterday')), $sql->getValue('col_date'));
        self::assertSame('lorem ipsum', $sql->getValue('col_text'));
    }

    public function testInsertOrUpdate(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('id', 1);
        $sql->setValue('col_int', 5);
        $sql->setValue('col_str', 'abc');

        $sql->insertOrUpdate();
        self::assertEquals(1, $sql->getRows());

        $sql->setTable(self::TABLE)->select();
        self::assertEquals(5, $sql->getValue('col_int'));
        self::assertEquals('abc', $sql->getValue('col_str'));

        $sql->setTable(self::TABLE);
        $sql->setValue('id', 1);
        $sql->setValue('col_int', 3);
        $sql->setValue('col_str', 'foo');

        $sql->insertOrUpdate();
        self::assertEquals(2, $sql->getRows());

        $sql->setTable(self::TABLE)->select();
        self::assertEquals(3, $sql->getValue('col_int'));
        self::assertEquals('foo', $sql->getValue('col_str'));
    }

    public function testInsertOrUpdateRecords(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 1);
            $record->setValue('col_str', 'foo');
        });
        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 2);
            $record->setValue('col_str', 'bar');
        });

        $sql->insertOrUpdate();

        self::assertSame(2, $sql->getRows());

        $sql->setTable(self::TABLE)->select();

        self::assertSame(2, $sql->getRows());

        self::assertEquals(1, $sql->getValue('id'));
        self::assertSame('foo', $sql->getValue('col_str'));

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 1);
            $record->setValue('col_str', 'abc');
        });
        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 3);
            $record->setValue('col_str', 'baz');
        });

        $sql->insertOrUpdate();

        self::assertSame(3, $sql->getRows());

        $sql->setTable(self::TABLE)->select();

        self::assertSame(3, $sql->getRows());

        self::assertEquals(1, $sql->getValue('id'));
        self::assertSame('abc', $sql->getValue('col_str'));

        $sql->next();
        self::assertEquals(2, $sql->getValue('id'));
        self::assertSame('bar', $sql->getValue('col_str'));

        $sql->next();
        self::assertEquals(3, $sql->getValue('id'));
        self::assertSame('baz', $sql->getValue('col_str'));
    }

    public function testReplaceRecords(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 1);
            $record->setValue('col_str', 'foo');
        });
        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 2);
            $record->setValue('col_str', 'bar');
        });

        $sql->replace();

        self::assertSame(2, $sql->getRows());

        $sql->setTable(self::TABLE)->select();

        self::assertSame(2, $sql->getRows());

        self::assertEquals(1, $sql->getValue('id'));
        self::assertSame('foo', $sql->getValue('col_str'));

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);

        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 1);
            $record->setValue('col_str', 'abc');
        });
        $sql->addRecord(static function (Sql $record) {
            $record->setValue('id', 3);
            $record->setValue('col_str', 'baz');
        });

        $sql->replace();

        self::assertSame(3, $sql->getRows());

        $sql->setTable(self::TABLE)->select();

        self::assertSame(3, $sql->getRows());

        self::assertEquals(1, $sql->getValue('id'));
        self::assertSame('abc', $sql->getValue('col_str'));

        $sql->next();
        self::assertEquals(2, $sql->getValue('id'));
        self::assertSame('bar', $sql->getValue('col_str'));

        $sql->next();
        self::assertEquals(3, $sql->getValue('id'));
        self::assertSame('baz', $sql->getValue('col_str'));
    }

    public function testUpdateRowByWhereArray(): void
    {
        // create a row we later update
        $this->testInsertRow();

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_str', 'def');
        $sql->setWhere(['col_int' => 5]);
        $sql->setValue('col_int', 6);

        $sql->update();
        self::assertEquals(1, $sql->getRows());

        $sql->setQuery('SELECT * FROM ' . self::TABLE);
        self::assertSame('def', $sql->getValue('col_str'));
        self::assertEquals(6, $sql->getValue('col_int'));
    }

    public function testUpdateRowByNamedWhere(): void
    {
        // create a row we later update
        $this->testInsertRow();

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_str', 'def');
        $sql->setWhere('col_int = :myint', ['myint' => 5]);

        $sql->update();
        self::assertEquals(1, $sql->getRows());
    }

    public function testUpdateRowByStringWhere(): void
    {
        // create a row we later update
        $this->testInsertRow();

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_str', 'def');
        $sql->setWhere('col_int = "5"');

        $sql->update();
        self::assertEquals(1, $sql->getRows());
    }

    public function testDeleteRow(): void
    {
        // create a row we later delete
        $this->testInsertRow();

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setWhere(['col_int' => 5]);

        $sql->delete();
        self::assertEquals(1, $sql->getRows());
    }

    public function testSelect(): void
    {
        $this->testInsertRow();

        // https://github.com/redaxo/core/issues/5518
        Sql::closeConnection();

        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setWhere('col_str = ' . $sql->escape('abc'));
        $sql->select();

        self::assertEquals(1, $sql->getRows());
    }

    public function testGetLastId(): void
    {
        $sql = Sql::factory();

        $exception = null;
        try {
            $sql->getLastId();
        } catch (SqlException $exception) {
        }
        self::assertInstanceOf(SqlException::class, $exception, 'LastId() without previous insert');

        $sql->setTable(self::TABLE);
        $sql->setValue('col_int', 5);
        $sql->setValue('col_str', 'abc');
        $sql->setValue('col_text', 'mytext');
        $sql->insert();

        self::assertSame(1, $sql->getLastId(), 'LastId after ->insert()');

        $sql->setTable(self::TABLE);
        $sql->setWhere(['id' => 1]);
        $sql->setValue('col_int', 6);
        $sql->update();

        $exception = null;
        try {
            $sql->getLastId();
        } catch (SqlException $exception) {
        }
        self::assertInstanceOf(SqlException::class, $exception, 'LastId after ->update()');

        $sql->setQuery('INSERT INTO ' . self::TABLE . ' SET col_int = 3');

        self::assertSame(2, $sql->getLastId(), 'LastId after second INSERT query');

        $secondSql = Sql::factory();
        $secondSql->setQuery('SELECT * FROM ' . self::TABLE);

        $exception = null;
        try {
            $secondSql->getLastId();
        } catch (SqlException $exception) {
        }
        self::assertInstanceOf(SqlException::class, $exception, 'LastId after SELECT query');

        self::assertSame(2, $sql->getLastId(), 'LastId still the same in other sql object');
    }

    public function testGetTables(): void
    {
        $tables = Sql::factory()->getTables();

        self::assertContains(self::TABLE, $tables);
        self::assertNotContains(self::VIEW, $tables);
    }

    public function testShowViews(): void
    {
        $views = Sql::factory()->getViews();

        self::assertNotContains(self::TABLE, $views);
        self::assertContains(self::VIEW, $views);
    }

    public function testShowTablesAndViews(): void
    {
        $tables = Sql::factory()->getTablesAndViews();

        self::assertContains(self::TABLE, $tables);
        self::assertContains(self::VIEW, $tables);
    }

    #[DataProvider('provideGetQueryTypes')]
    public function testGetQueryType(string $query, string|false $expectedQueryType): void
    {
        $actualQueryType = Sql::getQueryType($query);
        self::assertSame($expectedQueryType, $actualQueryType);
    }

    /** @return list<array{string, string|false}> */
    public static function provideGetQueryTypes(): array
    {
        return [
            ['Select * from testTable', 'SELECT'],
            ['(select * from testTable) union (select * from testTable)', 'SELECT'],
            [' ( SELECT * from testTable)', 'SELECT'],
            ['(DB2) (SELECT * from testTable)', 'SELECT'],
            ['shOW tables', 'SHOW'],
            ['update tableName set field=value', 'UPDATE'],
            ['insert into tableName set field=value', 'INSERT'],
            ['delete from tableName', 'DELETE'],
            ['rePlace into tableName set field=value', 'REPLACE'],
            ['create tableName', 'CREATE'],
            ['call someprocedure', 'CALL'],
            ['optimize tablename', 'OPTIMIZE'],
            ['dance to the beat :D', false],
        ];
    }

    public function testGetArrayKeyPair(): void
    {
        $this->testInsertRow();

        $sql = Sql::factory();
        $query = 'select col_str,col_int from ' . self::TABLE;
        $data = $sql->getArray($query, [], PDO::FETCH_KEY_PAIR);

        self::assertIsArray($data);
        self::assertCount(1, $data);
        foreach ($data as $k => $v) {
            self::assertIsInt($v);
            self::assertIsString($k);
        }
    }

    public function testGetDbArrayKeyPair(): void
    {
        $this->testInsertRow();

        $sql = Sql::factory();
        $query = 'select col_str,col_int from ' . self::TABLE;
        $data = $sql->getDBArray($query, [], PDO::FETCH_KEY_PAIR);

        self::assertIsArray($data);
        self::assertCount(1, $data);
        foreach ($data as $k => $v) {
            self::assertIsInt($v);
            self::assertIsString($k);
        }
    }
}
