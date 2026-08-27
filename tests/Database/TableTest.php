<?php

namespace Redaxo\Core\Tests\Database;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\ForeignKey;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Exception\LogicException;

/** @internal */
final class TableTest extends TestCase
{
    public const string TABLE = 'rex_sql_table_test';
    public const string TABLE2 = 'rex_sql_table_test2';

    #[Override]
    protected function tearDown(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE2 . '`');
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        Table::clearInstancePool();
    }

    private function createTable(): Table
    {
        $table = Table::get(self::TABLE);

        $table
            ->addColumn(new Column('id', 'int(11)', false, null, 'auto_increment', 'initial comment for id col'))
            ->addColumn(new Column('title', 'varchar(255)', true, 'Default title'))
            ->setPrimaryKey('id')
            ->addIndex(new Index('i_title', ['title']))
            ->create();

        return $table;
    }

    private function createTable2(): Table
    {
        $table = Table::get(self::TABLE2);

        $table
            ->addColumn(new Column('id', 'int(11)', false, null, 'auto_increment'))
            ->addColumn(new Column('test1_id', 'int(11)'))
            ->setPrimaryKey('id')
            ->addForeignKey(new ForeignKey('test2_fk_test1', self::TABLE, ['test1_id' => 'id']))
            ->create();

        return $table;
    }

    public function testCreate(): void
    {
        self::assertFalse(Table::get(self::TABLE)->exists());

        self::assertTrue($this->createTable()->exists());

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertTrue($table->exists());
        self::assertSame(self::TABLE, $table->getName());

        self::assertCount(2, $table->getColumns());
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('title'));
        self::assertFalse($table->hasColumn('foo'));
        self::assertSame(['id'], $table->getPrimaryKey());

        $id = $table->getColumn('id');

        self::assertInstanceOf(Column::class, $id);
        self::assertSame('id', $id->getName());
        self::assertSame('int', $id->getType());
        self::assertFalse($id->isNullable());
        self::assertNull($id->getDefault());
        self::assertSame('auto_increment', $id->getExtra());
        self::assertSame('initial comment for id col', $id->getComment());

        $title = $table->getColumn('title');

        self::assertInstanceOf(Column::class, $title);
        self::assertSame('title', $title->getName());
        self::assertSame('varchar(255)', $title->getType());
        self::assertTrue($title->isNullable());
        self::assertSame('Default title', $title->getDefault());
        self::assertNull($title->getExtra());

        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('i_title'));
        self::assertFalse($table->hasIndex('i_foo'));

        $index = $table->getIndex('i_title');

        self::assertInstanceOf(Index::class, $index);
        self::assertSame('i_title', $index->getName());
        self::assertSame(Index::INDEX, $index->getType());
        self::assertSame(['title'], $index->getColumns());

        self::assertTrue($this->createTable2()->exists());

        Table::clearInstance(self::TABLE2);
        $table2 = Table::get(self::TABLE2);

        self::assertCount(1, $table2->getForeignKeys());
        self::assertTrue($table2->hasForeignKey('test2_fk_test1'));
        self::assertFalse($table2->hasForeignKey('foo'));

        $fk = $table2->getForeignKey('test2_fk_test1');

        self::assertInstanceOf(ForeignKey::class, $fk);
        self::assertSame('test2_fk_test1', $fk->getName());
        self::assertSame(self::TABLE, $fk->getTable());
        self::assertSame(ForeignKey::RESTRICT, $fk->getOnUpdate());
        self::assertSame(ForeignKey::RESTRICT, $fk->getOnDelete());
        self::assertSame(['test1_id' => 'id'], $fk->getColumns());
    }

    public function testDrop(): void
    {
        $table = $this->createTable();

        $table->drop();

        self::assertFalse($table->exists());

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertFalse($table->exists());

        $table->drop();
    }

    public function testSetName(): void
    {
        $table = $this->createTable();

        $table
            ->setName(self::TABLE2)
            ->alter();

        self::assertFalse(Table::get(self::TABLE)->exists());

        Table::clearInstance(self::TABLE2);
        $table = Table::get(self::TABLE2);

        self::assertTrue($table->exists());
    }

    public function testAddColumn(): void
    {
        $table = $this->createTable();

        $description = new Column('description', 'text', true, null, null, 'description comment');
        $table
            ->addColumn($description)
            ->addColumn(new Column('name', 'varchar(255)'), 'id')
            ->addColumn(new Column('pid', 'int(11)'), Table::FIRST)
            ->alter();

        self::assertSame($description, $table->getColumn('description'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($description, $table->getColumn('description'));

        self::assertSame(['pid', 'id', 'name', 'title', 'description'], array_keys($table->getColumns()));
    }

    public function testAddColumnComment(): void
    {
        $table = $this->createTable();

        $title = new Column('title', 'varchar(20)', false, null, null, 'new title comment');
        $table
            ->ensureColumn($title)
            ->alter();

        self::assertSame($title, $table->getColumn('title'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($title, $table->getColumn('title'));
        self::assertSame('new title comment', $table->getColumn('title')?->getComment());
    }

    public function testChangeColumnComment(): void
    {
        $table = $this->createTable();

        $id = new Column('id', 'int(11)', false, null, 'auto_increment', 'changed id comment');
        $table
            ->ensureColumn($id)
            ->alter();

        self::assertSame($id, $table->getColumn('id'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($id, $table->getColumn('id'));
        self::assertSame('changed id comment', $table->getColumn('id')?->getComment());
    }

    public function testRemoveColumnComment(): void
    {
        $table = $this->createTable();

        $id = new Column('id', 'int(11)', false, null, 'auto_increment', null);
        $table
            ->ensureColumn($id)
            ->alter();

        self::assertSame($id, $table->getColumn('id'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        $idNew = $table->getColumn('id');
        self::assertInstanceOf(Column::class, $idNew);
        self::assertEquals($id, $idNew);
        self::assertNull($idNew->getComment());
    }

    public function testEnsureColumn(): void
    {
        $table = $this->createTable();

        $title = new Column('title', 'varchar(20)', false);
        $description = new Column('description', 'text', true);
        $table
            ->ensureColumn($description)
            ->ensureColumn($title, 'description')
            ->alter();

        self::assertSame($title, $table->getColumn('title'));
        self::assertSame($description, $table->getColumn('description'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($title, $table->getColumn('title'));
        self::assertEquals($description, $table->getColumn('description'));

        self::assertSame(['id', 'description', 'title'], array_keys($table->getColumns()));

        $status = new Column('status', 'tinyint(1)', false, '0');
        $amount = new Column('amount', 'int(5)', true);

        $table
            ->ensureColumn($title, 'id')
            ->ensureColumn($status, 'id')
            ->ensureColumn(new Column('created', 'datetime', false, 'CURRENT_TIMESTAMP'), 'status')
            ->ensureColumn($title, 'status')
            ->ensureColumn($amount)
            ->alter();

        $expectedOrder = ['id', 'status', 'title', 'created', 'description', 'amount'];

        self::assertSame($expectedOrder, array_keys($table->getColumns()));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertSame($expectedOrder, array_keys($table->getColumns())); // @phpstan-ignore argument.unresolvableType,   staticMethod.impossibleType

        self::assertEquals($status, $table->getColumn('status'));

        // The display width is normalized away, so the type does not depend on the database engine.
        self::assertEquals('int', $table->getColumn('amount')?->getType());
    }

    public function testEnsurePrimaryIdColumn(): void
    {
        $table = Table::get(self::TABLE);
        $table
            ->ensurePrimaryIdColumn()
            ->create();

        $id = $table->getColumn('id');
        self::assertInstanceOf(Column::class, $id);
        self::assertSame('int unsigned', $id->getType());
        self::assertFalse($id->isNullable());
        self::assertNull($id->getDefault());
        self::assertSame('auto_increment', $id->getExtra());
        self::assertSame(['id'], $table->getPrimaryKey());
    }

    public function testEnsureGlobalColumns(): void
    {
        $table = $this->createTable();
        $table
            ->ensureGlobalColumns()
            ->alter();

        self::assertTrue($table->hasColumn('createdate'));
        self::assertSame('datetime', $table->getColumn('createdate')?->getType());
        self::assertTrue($table->hasColumn('createuser'));
        self::assertSame('varchar(255)', $table->getColumn('createuser')?->getType());
        self::assertTrue($table->hasColumn('updatedate'));
        self::assertSame('datetime', $table->getColumn('updatedate')?->getType());
        self::assertTrue($table->hasColumn('updateuser'));
        self::assertSame('varchar(255)', $table->getColumn('updateuser')?->getType());
    }

    public function testRenameColumn(): void
    {
        $table = $this->createTable();

        $table->renameColumn('title', 'name');

        self::assertFalse($table->hasColumn('title'));
        self::assertTrue($table->hasColumn('name'));

        $table->alter();

        self::assertTrue($table->hasColumn('name'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertFalse($table->hasColumn('title'));
        self::assertTrue($table->hasColumn('name'));
        self::assertSame('varchar(255)', $table->getColumn('name')?->getType());

        $table
            ->renameColumn('id', 'pid')
            ->alter();

        self::assertSame(['pid'], $table->getPrimaryKey());

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertSame(['pid'], $table->getPrimaryKey());
    }

    public function testRenameColumnNonExisting(): void
    {
        $this->expectException(LogicException::class);

        $table = $this->createTable();
        $table->renameColumn('foo', 'bar');
    }

    public function testRenameColumnToAlreadyExisting(): void
    {
        $this->expectException(LogicException::class);

        $table = $this->createTable();
        $table->renameColumn('id', 'title');
    }

    public function testRemoveColumn(): void
    {
        $table = $this->createTable();

        $table
            ->removeColumn('title')
            ->alter();

        self::assertFalse($table->hasColumn('title'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertFalse($table->hasColumn('title'));
    }

    public function testSetPrimaryKey(): void
    {
        $table = $this->createTable();

        $primaryKey = ['id', 'title'];
        $table
            ->setPrimaryKey($primaryKey)
            ->alter();

        self::assertSame($primaryKey, $table->getPrimaryKey());

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertSame($primaryKey, $table->getPrimaryKey());

        $table->getColumn('id')->setExtra(null);
        $table
            ->setPrimaryKey(null)
            ->alter();

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertNull($table->getPrimaryKey());

        $table
            ->setPrimaryKey('id')
            ->alter();

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertSame(['id'], $table->getPrimaryKey());
    }

    public function testAddIndex(): void
    {
        $table = $this->createTable();

        $uuid = new Index('i_uuid', ['uuid'], Index::UNIQUE);
        $description = new Index('i_description', ['description'], Index::FULLTEXT);
        $search = new Index('i_search', ['title', 'description'], Index::FULLTEXT);

        $table
            ->addColumn(new Column('uuid', 'varchar(255)'))
            ->addColumn(new Column('description', 'text', true))
            ->addIndex($uuid)
            ->addIndex($description)
            ->addIndex($search)
            ->alter();

        self::assertSame($uuid, $table->getIndex('i_uuid'));
        self::assertSame($description, $table->getIndex('i_description'));
        self::assertSame($search, $table->getIndex('i_search'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($uuid, $table->getIndex('i_uuid'));
        self::assertEquals($description, $table->getIndex('i_description'));
        self::assertEquals($search, $table->getIndex('i_search'));
    }

    public function testEnsureIndex(): void
    {
        $table = $this->createTable();

        $title = new Index('i_title', ['title', 'title2'], Index::UNIQUE);
        $title2 = new Index('i_title2', ['title2']);
        $table
            ->ensureColumn(new Column('title2', 'varchar(20)'))
            ->ensureIndex($title)
            ->ensureIndex($title2)
            ->alter();

        self::assertSame($title, $table->getIndex('i_title'));
        self::assertSame($title2, $table->getIndex('i_title2'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($title, $table->getIndex('i_title'));
        self::assertEquals($title2, $table->getIndex('i_title2'));
    }

    public function testRenameIndex(): void
    {
        $table = $this->createTable();

        $table->renameIndex('i_title', 'index_title');

        self::assertFalse($table->hasIndex('i_title'));
        self::assertTrue($table->hasIndex('index_title'));

        $table->alter();

        self::assertTrue($table->hasIndex('index_title'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertFalse($table->hasIndex('i_title'));
        self::assertTrue($table->hasIndex('index_title'));
        self::assertSame(['title'], $table->getIndex('index_title')?->getColumns());
    }

    public function testRemoveIndex(): void
    {
        $table = $this->createTable();

        $table
            ->removeIndex('i_title')
            ->alter();

        self::assertFalse($table->hasColumn('i_title'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertFalse($table->hasColumn('i_title'));
    }

    public function testAddForeignKey(): void
    {
        $table = $this->createTable();

        $fk = new ForeignKey('test1_fk_config', 'rex_config', [
            'config_namespace' => 'namespace',
            'config_key' => 'key',
        ], ForeignKey::CASCADE, ForeignKey::SET_NULL);

        $table
            ->addColumn(new Column('config_namespace', 'varchar(75)', true))
            ->addColumn(new Column('config_key', 'varchar(255)', true))
            ->addForeignKey($fk)
            ->alter();

        self::assertSame($fk, $table->getForeignKey('test1_fk_config'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertEquals($fk, $table->getForeignKey('test1_fk_config'));

        // assertEquals compares arrays order-insensitively, so the column order needs its own assertion.
        self::assertSame(
            ['config_namespace' => 'namespace', 'config_key' => 'key'],
            $table->getForeignKey('test1_fk_config')?->getColumns(),
        );
    }

    public function testEnsureForeignKey(): void
    {
        $this->createTable();
        $table2 = $this->createTable2();

        $fk1 = new ForeignKey('test2_fk_test1', self::TABLE, [
            'test1_id' => 'id',
        ], ForeignKey::RESTRICT, ForeignKey::CASCADE);

        $fk2 = new ForeignKey('test2_fk_config', 'rex_config', [
            'config_namespace' => 'namespace',
            'config_key' => 'key',
        ], ForeignKey::CASCADE, ForeignKey::SET_NULL);

        $table2
            ->ensureColumn(new Column('config_namespace', 'varchar(75)', true))
            ->ensureColumn(new Column('config_key', 'varchar(255)', true))
            ->ensureForeignKey($fk1)
            ->ensureForeignKey($fk2)
            ->alter();

        self::assertSame($fk1, $table2->getForeignKey('test2_fk_test1'));
        self::assertSame($fk2, $table2->getForeignKey('test2_fk_config'));

        Table::clearInstance(self::TABLE2);
        $table2 = Table::get(self::TABLE2);

        self::assertEquals($fk1, $table2->getForeignKey('test2_fk_test1'));
        self::assertEquals($fk2, $table2->getForeignKey('test2_fk_config'));
    }

    public function testRenameForeignKey(): void
    {
        $this->createTable();
        $table2 = $this->createTable2();

        $table2->renameForeignKey('test2_fk_test1', 'fk_test2_test1');

        self::assertFalse($table2->hasForeignKey('test2_fk_test1'));
        self::assertTrue($table2->hasForeignKey('fk_test2_test1'));

        $table2->alter();

        self::assertTrue($table2->hasForeignKey('fk_test2_test1'));

        Table::clearInstance(self::TABLE2);
        $table2 = Table::get(self::TABLE2);

        self::assertFalse($table2->hasForeignKey('test2_fk_test1'));
        self::assertTrue($table2->hasForeignKey('fk_test2_test1'));
        self::assertSame(['test1_id' => 'id'], $table2->getForeignKey('fk_test2_test1')?->getColumns());
    }

    public function testRemoveForeignKey(): void
    {
        $this->createTable();
        $table2 = $this->createTable2();

        $table2
            ->removeForeignKey('test2_fk_test1')
            ->alter();

        self::assertFalse($table2->hasForeignKey('test2_fk_test1'));

        Table::clearInstance(self::TABLE2);
        $table2 = Table::get(self::TABLE2);

        self::assertFalse($table2->hasForeignKey('test2_fk_test1'));
    }

    public function testAlter(): void
    {
        $table = $this->createTable();

        $table->getColumn('id')->setType('int unsigned');
        $table
            ->setName(self::TABLE2)
            ->removeColumn('title')
            ->addColumn(new Column('name', 'varchar(20)'))
            ->setPrimaryKey(['id', 'name'])
            ->addIndex(new Index('i_name', ['name']))
            ->alter();

        Table::clearInstance(self::TABLE2);
        $table = Table::get(self::TABLE2);

        self::assertFalse($table->hasColumn('title'));
        self::assertFalse($table->hasIndex('i_title'));
        self::assertTrue($table->hasColumn('name'));
        self::assertTrue($table->hasIndex('i_name'));
        self::assertSame('int unsigned', $table->getColumn('id')?->getType());
        self::assertEquals(['id', 'name'], $table->getPrimaryKey());
        self::assertEquals(['name'], $table->getIndex('i_name')?->getColumns());
    }

    public function testEnsure(): void
    {
        $table = Table::get(self::TABLE);
        $table
            ->ensureColumn(new Column('title', 'varchar(255)', false, 'Default title'))
            ->ensureColumn(new Column('teaser', 'varchar(255)', false))
            ->ensureColumn(new Column('id', 'int(11)', false, null, 'auto_increment'), Table::FIRST)
            ->ensureColumn(new Column('status', 'tinyint(1)'))
            ->ensureColumn(new Column('timestamp', 'datetime', true))
            ->ensureColumn(new Column('description', 'text', true), 'title')
            ->setPrimaryKey('id')
            ->ensureIndex(new Index('i_status_timestamp', ['status', 'timestamp']))
            ->ensureIndex(new Index('i_description', ['description'], Index::FULLTEXT))
            ->ensure();

        self::assertTrue($table->exists());
        self::assertSame(['id', 'title', 'description', 'teaser', 'status', 'timestamp'], array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertTrue($table->hasIndex('i_description'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        $table
            ->ensureColumn(new Column('timestamp', 'datetime', true))
            ->ensureColumn(new Column('id', 'int(11)', false, null, 'auto_increment'))
            ->ensureColumn(new Column('status', 'tinyint(1)'))
            ->ensureColumn(new Column('title', 'varchar(20)', false), 'timestamp')
            ->ensureColumn(new Column('teaser', 'varchar(255)', false), 'status')
            ->setPrimaryKey(['id', 'title'])
            ->ensureIndex(new Index('i_status_timestamp', ['status', 'timestamp'], Index::UNIQUE))
            ->ensure();

        $expectedOrder = ['timestamp', 'title', 'id', 'status', 'teaser', 'description'];

        self::assertSame($expectedOrder, array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertSame(Index::UNIQUE, $table->getIndex('i_status_timestamp')?->getType());
        self::assertTrue($table->hasIndex('i_description'));

        Table::clearInstance(self::TABLE);
        $table = Table::get(self::TABLE);

        self::assertSame(['title', 'id'], $table->getPrimaryKey());
        self::assertTrue($table->hasColumn('description'));
        self::assertNull($table->getColumn('title')->getDefault());
        self::assertSame($expectedOrder, array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertSame(Index::UNIQUE, $table->getIndex('i_status_timestamp')?->getType());
        self::assertTrue($table->hasIndex('i_description'));
    }

    #[DoesNotPerformAssertions]
    public function testEnsureMultipleTimes(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            Table::get(self::TABLE)
                ->ensurePrimaryIdColumn()
                ->ensureColumn(new Column('title', 'varchar(255)'))
                ->ensure();
        }
    }

    #[DataProvider('provideIntegerTypes')]
    public function testEnsureIntegerColumn(string $type): void
    {
        $column = new Column('foo', $type, true);

        Table::get(self::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn($column)
            ->ensure();

        Table::clearInstance(self::TABLE);

        self::assertEquals($column, Table::get(self::TABLE)->getColumn('foo'));
    }

    /** @return iterable<string, array{string}> */
    public static function provideIntegerTypes(): iterable
    {
        $types = [
            'tinyint(4)', 'tinyint(3) unsigned', 'tinyint(1)', 'tinyint', 'tinyint unsigned',
            'smallint(6)', 'smallint(5) unsigned', 'smallint', 'smallint unsigned',
            'mediumint(9)', 'mediumint(8) unsigned', 'mediumint', 'mediumint unsigned',
            'int(11)', 'int(10) unsigned', 'int', 'int unsigned', 'int(5)',
            'bigint(20)', 'bigint(20) unsigned', 'bigint', 'bigint unsigned',
            'int(4) unsigned zerofill',
        ];

        foreach ($types as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('provideCurrentTimestampColumns')]
    public function testEnsureCurrentTimestampColumn(Column $column, string $expectedDefault, ?string $expectedExtra): void
    {
        Table::get(self::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn($column)
            ->ensure();

        Table::clearInstance(self::TABLE);

        $readColumn = Table::get(self::TABLE)->getColumn('foo');
        self::assertInstanceOf(Column::class, $readColumn);

        self::assertSame($expectedDefault, $readColumn->getDefault());
        self::assertSame($expectedExtra, $readColumn->getExtra());
        self::assertTrue($column->equals($readColumn));
    }

    /** @return iterable<string, array{Column, string, string|null}> */
    public static function provideCurrentTimestampColumns(): iterable
    {
        yield 'datetime' => [
            new Column('foo', 'datetime', false, 'CURRENT_TIMESTAMP'),
            'CURRENT_TIMESTAMP', null,
        ];
        yield 'datetime, function spelling' => [
            new Column('foo', 'datetime', false, 'current_timestamp()'),
            'CURRENT_TIMESTAMP', null,
        ];
        yield 'datetime with precision' => [
            new Column('foo', 'datetime(3)', false, 'current_timestamp(3)'),
            'CURRENT_TIMESTAMP(3)', null,
        ];
        yield 'timestamp, on update' => [
            new Column('foo', 'timestamp', false, 'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'),
            'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP',
        ];
        yield 'timestamp, on update function spelling' => [
            new Column('foo', 'timestamp', false, 'current_timestamp()', 'on update current_timestamp()'),
            'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP',
        ];
    }

    public function testEnsureWithEnsureGlobalColumns(): void
    {
        $expectedOrder = ['id', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser', 'revision'];

        for ($i = 1; $i <= 2; ++$i) {
            $table = Table::get(self::TABLE);
            $table
                ->ensurePrimaryIdColumn()
                ->ensureColumn(new Column('title', 'varchar(255)'))
                ->ensureGlobalColumns()
                ->ensureColumn(new Column('revision', 'tinyint(1)'))
                ->ensure();

            Table::clearInstance(self::TABLE);
            $table = Table::get(self::TABLE);

            self::assertSame($expectedOrder, array_keys($table->getColumns()), "Column order does not match expected order (\$i = $i)");
        }
    }

    public function testRenameNonExistingTable(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Table "rex_non_existing" does not exist.');

        Table::get('rex_non_existing')
            ->setName('rex_foo')
            ->alter();
    }

    public function testClearInstance(): void
    {
        $table = Table::get(self::TABLE);

        Table::clearInstance(self::TABLE);
        $table2 = Table::get(self::TABLE);

        self::assertNotSame($table2, $table);

        Table::clearInstance(self::TABLE, 1);
        $table3 = Table::get(self::TABLE);

        self::assertNotSame($table3, $table);
        self::assertNotSame($table3, $table2);
    }
}
