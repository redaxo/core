<?php

namespace Redaxo\Core\Tests\Database;

use Override;
use PDO;
use Pdo\Mysql;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use ReflectionProperty;

/** @internal */
final class SelectTest extends TestCase
{
    public const string TABLE = 'rex_tests';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $sql = Sql::factory();

        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $sql->setQuery('CREATE TABLE `' . self::TABLE . '` (
            `id` INT NOT NULL AUTO_INCREMENT ,
            `col_str` VARCHAR( 255 ) NOT NULL ,
            `col_int` INT NOT NULL ,
            `col_date` DATE NULL ,
            `col_time` DATETIME NULL ,
            `col_text` TEXT NOT NULL ,
            PRIMARY KEY ( `id` )
            ) ENGINE = InnoDB ;');

        // Insert a row for later selection tests
        $this->insertRow();
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        // Drops the table and all therefore all its rows
        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE `' . self::TABLE . '`');
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '_join`');
    }

    public function testGetRow(): void
    {
        // we need some rows for this test
        $this->insertRow();
        $this->insertRow();

        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_int = ?', [5]);

        self::assertCount(6, $sql->getRow(), 'getRow() returns an array containing all columns of the ResultSet');
        self::assertEquals(3, $sql->getRows(), 'getRows() returns the number of rows');

        foreach ($sql as $row) {
            self::assertTrue($row->hasValue('col_str'), 'values exist in each row');
            self::assertTrue($row->hasValue('col_int'), 'values exist in each row');

            self::assertEquals('abc', $row->getValue('col_str'), 'get a string');
            self::assertEquals(5, $row->getValue('col_int'), 'get an int ');

            self::assertEquals('abc', $row->getValue(self::TABLE . '.col_str'), 'get a string with table.col notation');
            self::assertEquals(5, $row->getValue(self::TABLE . '.col_int'), 'get an int with table.col notation');
        }
    }

    public function testGetVariations(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5');

        self::assertEquals(1, $sql->getRows());

        self::assertTrue($sql->hasValue('col_str'), 'hasValue() checks field by name');
        self::assertTrue($sql->hasValue(self::TABLE . '.col_str'), 'hasValue() checks field by table.fieldname');

        self::assertEquals('abc', $sql->getValue('col_str'), 'getValue() retrievs field by name');
        self::assertEquals('abc', $sql->getValue(self::TABLE . '.col_str'), 'getValue() retrievs field by table.fieldname');

        self::assertSame(['id', 'col_str', 'col_int', 'col_date', 'col_time', 'col_text'], $sql->getFieldnames());
    }

    public function testJoinWithAmbiguousColumns(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('CREATE TABLE `' . self::TABLE . '_join` (`id` INT NOT NULL, `col_str` VARCHAR(255) NOT NULL, `col_other` INT NOT NULL)');
        $sql->setQuery('INSERT INTO `' . self::TABLE . '_join` VALUES (1, "joined", 7)');

        $sql->setQuery('SELECT a.*, b.* FROM ' . self::TABLE . ' a JOIN ' . self::TABLE . '_join b ON a.id = b.id');

        self::assertSame(['a', 'b'], $sql->getTablenames());
        self::assertSame(['id', 'col_str', 'col_int', 'col_date', 'col_time', 'col_text', 'id', 'col_str', 'col_other'], $sql->getFieldnames());

        self::assertTrue($sql->hasValue('col_str'));
        self::assertTrue($sql->hasValue('a.col_str'));
        self::assertTrue($sql->hasValue('b.col_str'));
        self::assertFalse($sql->hasValue('b.col_int'), 'hasValue() checks the column of the given table');
        self::assertFalse($sql->hasValue('c.col_str'));

        self::assertSame('abc', $sql->getValue('col_str'), 'ambiguous columns resolve to the first occurrence');
        self::assertSame('abc', $sql->getValue('a.col_str'));
        self::assertSame('joined', $sql->getValue('b.col_str'));
        self::assertSame(7, $sql->getValue('col_other'));
        self::assertSame(7, $sql->getValue('b.col_other'));

        $row = $sql->getRow();
        self::assertSame(['id', 'col_str', 'col_int', 'col_date', 'col_time', 'col_text', 'col_other'], array_keys($row));
        self::assertSame('joined', $row['col_str'], 'getRow() resolves ambiguous columns like PDO::FETCH_ASSOC (last occurrence)');
        $numRow = $sql->getRow(PDO::FETCH_NUM);
        self::assertCount(9, $numRow);

        $array = $sql->getArray();
        self::assertCount(1, $array);
        self::assertSame($row, $array[0]);

        $array = $sql->getArray(null, [], PDO::FETCH_NUM);
        self::assertSame($numRow, $array[0]);
    }

    public function testExpressionColumns(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT COUNT(*) AS cnt, MAX(col_int), "lit" AS lit FROM ' . self::TABLE);

        self::assertSame([], $sql->getTablenames());
        self::assertSame(['cnt', 'MAX(col_int)', 'lit'], $sql->getFieldnames());

        self::assertTrue($sql->hasValue('cnt'));
        self::assertSame(1, $sql->getValue('cnt'));
        self::assertSame(5, $sql->getValue('MAX(col_int)'));
        self::assertSame('lit', $sql->getValue('lit'));

        self::assertSame(['cnt' => 1, 'MAX(col_int)' => 5, 'lit' => 'lit'], $sql->getRow());
        self::assertSame([['cnt' => 1, 'MAX(col_int)' => 5, 'lit' => 'lit']], $sql->getArray());
    }

    public function testGetArrayExecutesQueryOnlyOnce(): void
    {
        $sql = new class extends Sql {
            public int $executions = 0;

            public function __construct(int $db = 1)
            {
                parent::__construct($db);
            }

            public function execute(array $params = [], array $options = []): static
            {
                ++$this->executions;
                parent::execute($params, $options);

                return $this;
            }
        };

        $sql->setTable(self::TABLE);
        $sql->setWhere(['col_int' => 5]);
        $sql->select();
        self::assertSame(1, $sql->getRows());

        $array = $sql->getArray();
        self::assertSame(1, $sql->executions, 'getArray() reuses the result of the previous query');
        self::assertCount(1, $array);
        self::assertSame('abc', $array[0]['col_str']);

        $array = $sql->getArray();
        self::assertSame(2, $sql->executions, 'a second getArray() must execute the query again');
        self::assertCount(1, $array);

        $sql->setQuery('SELECT * FROM ' . self::TABLE);
        $values = [];
        foreach ($sql as $row) {
            $values[] = $row->getValue('col_str');
        }
        self::assertSame(['abc'], $values);
        self::assertSame(3, $sql->executions, 'iterating the result does not execute the query again');
    }

    public function testGetArrayAfterFetchingRows(): void
    {
        $this->insertRow();
        $this->insertRow();

        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' ORDER BY id');
        self::assertSame(1, $sql->getValue('id'), 'fetches the first row');
        self::assertCount(3, $sql->getArray(), 'getArray() contains the already fetched row');

        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' ORDER BY id');
        foreach ($sql as $row) {
            if (2 === $row->getValue('id')) {
                break;
            }
        }
        self::assertCount(3, $sql->getArray(), 'getArray() contains all rows after a partial iteration');

        self::assertCount(3, $sql->getArray('SELECT * FROM ' . self::TABLE), 'getArray() with an explicit query ignores the previous result');

        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' ORDER BY id');
        $sql->getArray();
        self::assertFalse($sql->hasNext(), 'the cursor is at the end after getArray()');
        $ids = [];
        foreach ($sql as $row) {
            $ids[] = $row->getValue('id');
        }
        self::assertSame([1, 2, 3], $ids, 'iterating after getArray() starts from the beginning');
    }

    public function testGetArrayWithoutQuery(): void
    {
        $sql = Sql::factory();

        $this->expectException(SqlException::class);
        $sql->getArray();
    }

    public function testGetArray(): void
    {
        $sql = Sql::factory();
        $array = $sql->getArray('SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5');

        self::assertEquals(1, $sql->getRows(), 'getRows() returns the number of rows');
        self::assertCount(1, $array, 'the returned array contain the correct number of rows');
        self::assertArrayHasKey(0, $array);

        $row1 = $array[0];
        self::assertEquals('abc', $row1['col_str']);
        self::assertEquals('5', $row1['col_int']);

        self::assertSame(['id', 'col_str', 'col_int', 'col_date', 'col_time', 'col_text'], $sql->getFieldnames());
    }

    public function testGetDbArray(): void
    {
        $sql = Sql::factory();
        $array = $sql->getDBArray('(DB1) SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5');

        self::assertEquals(1, $sql->getRows(), 'getRows() returns the number of rows');
        self::assertCount(1, $array, 'the returned array contain the correct number of rows');
        self::assertArrayHasKey(0, $array);

        $row1 = $array[0];
        self::assertEquals('abc', $row1['col_str']);
        self::assertEquals('5', $row1['col_int']);
    }

    public function testPreparedSetQuery(): void
    {
        $this->insertRow();

        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_str = ? and col_int = ? LIMIT ?', ['abc', 5, 1]);

        self::assertEquals(1, $sql->getRows());
    }

    public function testPreparedNamedSetQuery(): void
    {
        $this->insertRow();

        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_str = :mystr and col_int = :myint LIMIT :limit', ['mystr' => 'abc', ':myint' => 5, 'limit' => 1]);

        self::assertEquals(1, $sql->getRows());
    }

    public function testPreparedSetQueryWithReset(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_str = ? and col_int = ?', ['abc', 5]);

        $sql->reset();

        self::assertEquals(1, $sql->getRows());
    }

    public function testGetArrayAfterPreparedSetQuery(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_int = ?', [5]);
        $array = $sql->getArray();

        self::assertEquals(1, $sql->getRows());
        self::assertArrayHasKey(0, $array);

        $row1 = $array[0];
        self::assertEquals('abc', $row1['col_str']);
        self::assertEquals('5', $row1['col_int']);
    }

    public function testGetArrayAfterSetQuery(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5');
        $array = $sql->getArray();

        self::assertEquals(1, $sql->getRows());
        self::assertArrayHasKey(0, $array);

        $row1 = $array[0];
        self::assertEquals('abc', $row1['col_str']);
        self::assertEquals('5', $row1['col_int']);
    }

    public function testArrayFetchTypeNum(): void
    {
        $sql = Sql::factory();
        $array = $sql->getArray('SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5', [], PDO::FETCH_NUM);

        $row1 = $array[0];
        self::assertEquals('abc', $row1[1]);
        self::assertEquals('5', $row1[2]);
        self::assertEquals('mytext', $row1[5]);
        self::assertEquals('mytext', $row1[5]);
    }

    public function testDBArrayFetchTypeNum(): void
    {
        $sql = Sql::factory();
        $array = $sql->getDBArray('SELECT * FROM ' . self::TABLE . ' WHERE col_int = 5', [], PDO::FETCH_NUM);

        $row1 = $array[0];
        self::assertEquals('abc', $row1[1]);
        self::assertEquals('5', $row1[2]);
        self::assertEquals('mytext', $row1[5]);
        self::assertEquals('mytext', $row1[5]);
    }

    public function testHasNext(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . self::TABLE);

        self::assertTrue($sql->hasNext());

        $sql->next();
        self::assertFalse($sql->hasNext());

        $sql->next();
        self::assertFalse($sql->hasNext());
    }

    public function testError(): void
    {
        $sql = Sql::factory();

        $sql->setQuery('SELECT * FROM ' . self::TABLE);

        self::assertFalse($sql->hasError());
        self::assertEquals(0, $sql->getErrno());

        $exception = null;
        try {
            $sql->setQuery('SELECT ' . self::TABLE);
        } catch (SqlException $exception) {
        }

        self::assertInstanceOf(SqlException::class, $exception);
        self::assertTrue($sql->hasError());
        self::assertEquals('42S22', $sql->getErrno());
        self::assertEquals(1054, $sql->getMysqlErrno());
        self::assertNotNull($error = $sql->getError());
        self::assertStringStartsWith("Unknown column 'rex_tests' in ", $error);

        $exception = null;
        try {
            $sql->setQuery('SELECT * FROM ' . self::TABLE . ' WHERE idx = ?', [1]);
        } catch (SqlException $exception) {
        }

        self::assertInstanceOf(SqlException::class, $exception);
        self::assertTrue($sql->hasError());
        self::assertEquals('42S22', $sql->getErrno());
        self::assertEquals(1054, $sql->getMysqlErrno());
        self::assertNotNull($error = $sql->getError());
        self::assertStringStartsWith("Unknown column 'idx' in ", $error);

        $exception = null;
        Sql::closeConnection(); // https://github.com/redaxo/core/pull/5272#discussion_r935793505
        $sql = Sql::factory();
        try {
            $sql->setQuery('SELECT * FROM non_existing_table');
        } catch (SqlException $exception) {
        }

        self::assertInstanceOf(SqlException::class, $exception);
        self::assertSame($sql, $exception->sql);
        self::assertTrue($sql->hasError());
        self::assertSame(Sql::ERRNO_TABLE_OR_VIEW_DOESNT_EXIST, $sql->getErrno());
    }

    public function testUnbufferedQuery(): void
    {
        $sql = Sql::factory();

        // get DB 1 PDO object
        $property = new ReflectionProperty(Sql::class, 'pdo');
        /** @var PDO $pdo */
        $pdo = $property->getValue()[1];

        self::assertEquals(1, $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY));

        $sql->setQuery('SELECT * FROM ' . self::TABLE, [], [
            Sql::OPT_BUFFERED => false,
        ]);

        self::assertEquals(1, $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY));

        try {
            $sql->setQuery('SELECT ' . self::TABLE, [], [
                Sql::OPT_BUFFERED => false,
            ]);
        } catch (SqlException) {
        }

        self::assertEquals(1, $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY));
    }

    private function insertRow(): void
    {
        $sql = Sql::factory();
        $sql->setTable(self::TABLE);
        $sql->setValue('col_int', 5);
        $sql->setValue('col_str', 'abc');
        $sql->setValue('col_text', 'mytext');

        $sql->insert();
    }
}
