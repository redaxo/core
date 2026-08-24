<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_sql_table_test extends TestCase
{
    public const TABLE = 'rex_sql_table_test';
    public const TABLE2 = 'rex_sql_table_test2';

    protected function tearDown(): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE2 . '`');
        $sql->setQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        rex_sql_table::clearInstancePool();
    }

    private function createTable(): rex_sql_table
    {
        $table = rex_sql_table::get(self::TABLE);

        $table
            ->addColumn(new rex_sql_column('id', 'int(11)', false, null, 'auto_increment', 'initial comment for id col'))
            ->addColumn(new rex_sql_column('title', 'varchar(255)', true, 'Default title'))
            ->setPrimaryKey('id')
            ->addIndex(new rex_sql_index('i_title', ['title']))
            ->create();

        return $table;
    }

    private function createTable2(): rex_sql_table
    {
        $table = rex_sql_table::get(self::TABLE2);

        $table
            ->addColumn(new rex_sql_column('id', 'int(11)', false, null, 'auto_increment'))
            ->addColumn(new rex_sql_column('test1_id', 'int(11)'))
            ->setPrimaryKey('id')
            ->addForeignKey(new rex_sql_foreign_key('test2_fk_test1', self::TABLE, ['test1_id' => 'id']))
            ->create();

        return $table;
    }

    public function testCreate(): void
    {
        self::assertFalse(rex_sql_table::get(self::TABLE)->exists());

        self::assertTrue($this->createTable()->exists());

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertTrue($table->exists());
        self::assertSame(self::TABLE, $table->getName());

        self::assertCount(2, $table->getColumns());
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('title'));
        self::assertFalse($table->hasColumn('foo'));
        self::assertSame(['id'], $table->getPrimaryKey());

        $id = $table->getColumn('id');

        self::assertInstanceOf(rex_sql_column::class, $id);
        self::assertSame('id', $id->getName());
        self::assertSame('int(11)', $id->getType());
        self::assertFalse($id->isNullable());
        self::assertNull($id->getDefault());
        self::assertSame('auto_increment', $id->getExtra());
        self::assertSame('initial comment for id col', $id->getComment());

        $title = $table->getColumn('title');

        self::assertInstanceOf(rex_sql_column::class, $title);
        self::assertSame('title', $title->getName());
        self::assertSame('varchar(255)', $title->getType());
        self::assertTrue($title->isNullable());
        self::assertSame('Default title', $title->getDefault());
        self::assertNull($title->getExtra());

        self::assertCount(1, $table->getIndexes());
        self::assertTrue($table->hasIndex('i_title'));
        self::assertFalse($table->hasIndex('i_foo'));

        $index = $table->getIndex('i_title');

        self::assertInstanceOf(rex_sql_index::class, $index);
        self::assertSame('i_title', $index->getName());
        self::assertSame(rex_sql_index::INDEX, $index->getType());
        self::assertSame(['title'], $index->getColumns());

        self::assertTrue($this->createTable2()->exists());

        rex_sql_table::clearInstance(self::TABLE2);
        $table2 = rex_sql_table::get(self::TABLE2);

        self::assertCount(1, $table2->getForeignKeys());
        self::assertTrue($table2->hasForeignKey('test2_fk_test1'));
        self::assertFalse($table2->hasForeignKey('foo'));

        $fk = $table2->getForeignKey('test2_fk_test1');

        self::assertInstanceOf(rex_sql_foreign_key::class, $fk);
        self::assertSame('test2_fk_test1', $fk->getName());
        self::assertSame(self::TABLE, $fk->getTable());
        self::assertSame(rex_sql_foreign_key::RESTRICT, $fk->getOnUpdate());
        self::assertSame(rex_sql_foreign_key::RESTRICT, $fk->getOnDelete());
        self::assertSame(['test1_id' => 'id'], $fk->getColumns());
    }

    public function testDrop(): void
    {
        $table = $this->createTable();

        $table->drop();

        self::assertFalse($table->exists());

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertFalse($table->exists());

        $table->drop();
    }

    public function testSetName(): void
    {
        $table = $this->createTable();

        $table
            ->setName(self::TABLE2)
            ->alter();

        self::assertFalse(rex_sql_table::get(self::TABLE)->exists());

        rex_sql_table::clearInstance(self::TABLE2);
        $table = rex_sql_table::get(self::TABLE2);

        self::assertTrue($table->exists());
    }

    public function testAddColumn(): void
    {
        $table = $this->createTable();

        $description = new rex_sql_column('description', 'text', true, null, null, 'description comment');
        $table
            ->addColumn($description)
            ->addColumn(new rex_sql_column('name', 'varchar(255)'), 'id')
            ->addColumn(new rex_sql_column('pid', 'int(11)'), rex_sql_table::FIRST)
            ->alter();

        self::assertSame($description, $table->getColumn('description'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertEquals($description, $table->getColumn('description'));

        self::assertSame(['pid', 'id', 'name', 'title', 'description'], array_keys($table->getColumns()));
    }

    public function testAddColumnComment(): void
    {
        $table = $this->createTable();

        $title = new rex_sql_column('title', 'varchar(20)', false, null, null, 'new title comment');
        $table
            ->ensureColumn($title)
            ->alter();

        self::assertSame($title, $table->getColumn('title'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertEquals($title, $table->getColumn('title'));
        self::assertSame('new title comment', $table->getColumn('title')?->getComment());
    }

    public function testChangeColumnComment(): void
    {
        $table = $this->createTable();

        $id = new rex_sql_column('id', 'int(11)', false, null, 'auto_increment', 'changed id comment');
        $table
            ->ensureColumn($id)
            ->alter();

        self::assertSame($id, $table->getColumn('id'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertEquals($id, $table->getColumn('id'));
        self::assertSame('changed id comment', $table->getColumn('id')?->getComment());
    }

    public function testRemoveColumnComment(): void
    {
        $table = $this->createTable();

        $id = new rex_sql_column('id', 'int(11)', false, null, 'auto_increment', null);
        $table
            ->ensureColumn($id)
            ->alter();

        self::assertSame($id, $table->getColumn('id'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        $idNew = $table->getColumn('id');
        self::assertInstanceOf(rex_sql_column::class, $idNew);
        self::assertEquals($id, $idNew);
        self::assertNull($idNew->getComment());
    }

    public function testEnsureColumn(): void
    {
        $table = $this->createTable();

        $title = new rex_sql_column('title', 'varchar(20)', false);
        $description = new rex_sql_column('description', 'text', true);
        $table
            ->ensureColumn($description)
            ->ensureColumn($title, 'description')
            ->alter();

        self::assertSame($title, $table->getColumn('title'));
        self::assertSame($description, $table->getColumn('description'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertEquals($title, $table->getColumn('title'));
        self::assertEquals($description, $table->getColumn('description'));

        self::assertSame(['id', 'description', 'title'], array_keys($table->getColumns()));

        $status = new rex_sql_column('status', 'tinyint(1)', false, '0');
        $amount = new rex_sql_column('amount', 'int(5)', true);

        $table
            ->ensureColumn($title, 'id')
            ->ensureColumn($status, 'id')
            ->ensureColumn(new rex_sql_column('created', 'datetime', false, 'CURRENT_TIMESTAMP'), 'status')
            ->ensureColumn($title, 'status')
            ->ensureColumn($amount)
            ->alter();

        $expectedOrder = ['id', 'status', 'title', 'created', 'description', 'amount'];

        self::assertSame($expectedOrder, array_keys($table->getColumns()));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertSame($expectedOrder, array_keys($table->getColumns()));

        self::assertEquals($status, $table->getColumn('status'));

        $sql = rex_sql::factory();
        if (rex_sql::MYSQL === $sql->getDbType() && 8 <= (int) $sql->getDbVersion()) {
            // In MySQL 8 the display width of integers is simulated by rex_sql_table to the max width.
            self::assertEquals('int(11)', $table->getColumn('amount')?->getType());
        } else {
            self::assertEquals('int(5)', $table->getColumn('amount')?->getType());
        }
    }

    public function testEnsurePrimaryIdColumn(): void
    {
        $table = rex_sql_table::get(self::TABLE);
        $table
            ->ensurePrimaryIdColumn()
            ->create();

        $id = $table->getColumn('id');
        self::assertInstanceOf(rex_sql_column::class, $id);
        self::assertSame('int(10) unsigned', $id->getType());
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

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertFalse($table->hasColumn('title'));
        self::assertTrue($table->hasColumn('name'));
        self::assertSame('varchar(255)', $table->getColumn('name')?->getType());

        $table
            ->renameColumn('id', 'pid')
            ->alter();

        self::assertSame(['pid'], $table->getPrimaryKey());

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertSame(['pid'], $table->getPrimaryKey());
    }

    public function testRenameColumnNonExisting(): void
    {
        $this->expectException(rex_exception::class);

        $table = $this->createTable();
        $table->renameColumn('foo', 'bar');
    }

    public function testRenameColumnToAlreadyExisting(): void
    {
        $this->expectException(rex_exception::class);

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

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

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

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertSame($primaryKey, $table->getPrimaryKey());

        $table->getColumn('id')->setExtra(null);
        $table
            ->setPrimaryKey(null)
            ->alter();

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertNull($table->getPrimaryKey());

        $table
            ->setPrimaryKey('id')
            ->alter();

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertSame(['id'], $table->getPrimaryKey());
    }

    public function testAddIndex(): void
    {
        $table = $this->createTable();

        $uuid = new rex_sql_index('i_uuid', ['uuid'], rex_sql_index::UNIQUE);
        $description = new rex_sql_index('i_description', ['description'], rex_sql_index::FULLTEXT);
        $search = new rex_sql_index('i_search', ['title', 'description'], rex_sql_index::FULLTEXT);

        $table
            ->addColumn(new rex_sql_column('uuid', 'varchar(255)'))
            ->addColumn(new rex_sql_column('description', 'text', true))
            ->addIndex($uuid)
            ->addIndex($description)
            ->addIndex($search)
            ->alter();

        self::assertSame($uuid, $table->getIndex('i_uuid'));
        self::assertSame($description, $table->getIndex('i_description'));
        self::assertSame($search, $table->getIndex('i_search'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertEquals($uuid, $table->getIndex('i_uuid'));
        self::assertEquals($description, $table->getIndex('i_description'));
        self::assertEquals($search, $table->getIndex('i_search'));
    }

    public function testEnsureIndex(): void
    {
        $table = $this->createTable();

        $title = new rex_sql_index('i_title', ['title', 'title2'], rex_sql_index::UNIQUE);
        $title2 = new rex_sql_index('i_title2', ['title2']);
        $table
            ->ensureColumn(new rex_sql_column('title2', 'varchar(20)'))
            ->ensureIndex($title)
            ->ensureIndex($title2)
            ->alter();

        self::assertSame($title, $table->getIndex('i_title'));
        self::assertSame($title2, $table->getIndex('i_title2'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

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

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

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

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertFalse($table->hasColumn('i_title'));
    }

    public function testAddForeignKey(): void
    {
        $table = $this->createTable();

        $fk = new rex_sql_foreign_key('test1_fk_config', 'rex_config', [
            'config_namespace' => 'namespace',
            'config_key' => 'key',
        ], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::SET_NULL);

        $table
            ->addColumn(new rex_sql_column('config_namespace', 'varchar(75)', true))
            ->addColumn(new rex_sql_column('config_key', 'varchar(255)', true))
            ->addForeignKey($fk)
            ->alter();

        self::assertSame($fk, $table->getForeignKey('test1_fk_config'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

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

        $fk1 = new rex_sql_foreign_key('test2_fk_test1', self::TABLE, [
            'test1_id' => 'id',
        ], rex_sql_foreign_key::RESTRICT, rex_sql_foreign_key::CASCADE);

        $fk2 = new rex_sql_foreign_key('test2_fk_config', 'rex_config', [
            'config_namespace' => 'namespace',
            'config_key' => 'key',
        ], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::SET_NULL);

        $table2
            ->ensureColumn(new rex_sql_column('config_namespace', 'varchar(75)', true))
            ->ensureColumn(new rex_sql_column('config_key', 'varchar(255)', true))
            ->ensureForeignKey($fk1)
            ->ensureForeignKey($fk2)
            ->alter();

        self::assertSame($fk1, $table2->getForeignKey('test2_fk_test1'));
        self::assertSame($fk2, $table2->getForeignKey('test2_fk_config'));

        rex_sql_table::clearInstance(self::TABLE2);
        $table2 = rex_sql_table::get(self::TABLE2);

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

        rex_sql_table::clearInstance(self::TABLE2);
        $table2 = rex_sql_table::get(self::TABLE2);

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

        rex_sql_table::clearInstance(self::TABLE2);
        $table2 = rex_sql_table::get(self::TABLE2);

        self::assertFalse($table2->hasForeignKey('test2_fk_test1'));
    }

    public function testAlter(): void
    {
        $table = $this->createTable();

        $table->getColumn('id')->setType('int(10) unsigned');
        $table
            ->setName(self::TABLE2)
            ->removeColumn('title')
            ->addColumn(new rex_sql_column('name', 'varchar(20)'))
            ->setPrimaryKey(['id', 'name'])
            ->addIndex(new rex_sql_index('i_name', ['name']))
            ->alter();

        rex_sql_table::clearInstance(self::TABLE2);
        $table = rex_sql_table::get(self::TABLE2);

        self::assertFalse($table->hasColumn('title'));
        self::assertFalse($table->hasIndex('i_title'));
        self::assertTrue($table->hasColumn('name'));
        self::assertTrue($table->hasIndex('i_name'));
        self::assertSame('int(10) unsigned', $table->getColumn('id')?->getType());
        self::assertEquals(['id', 'name'], $table->getPrimaryKey());
        self::assertEquals(['name'], $table->getIndex('i_name')?->getColumns());
    }

    public function testEnsure(): void
    {
        $table = rex_sql_table::get(self::TABLE);
        $table
            ->ensureColumn(new rex_sql_column('title', 'varchar(255)', false, 'Default title'))
            ->ensureColumn(new rex_sql_column('teaser', 'varchar(255)', false))
            ->ensureColumn(new rex_sql_column('id', 'int(11)', false, null, 'auto_increment'), rex_sql_table::FIRST)
            ->ensureColumn(new rex_sql_column('status', 'tinyint(1)'))
            ->ensureColumn(new rex_sql_column('timestamp', 'datetime', true))
            ->ensureColumn(new rex_sql_column('description', 'text', true), 'title')
            ->setPrimaryKey('id')
            ->ensureIndex(new rex_sql_index('i_status_timestamp', ['status', 'timestamp']))
            ->ensureIndex(new rex_sql_index('i_description', ['description'], rex_sql_index::FULLTEXT))
            ->ensure();

        self::assertTrue($table->exists());
        self::assertSame(['id', 'title', 'description', 'teaser', 'status', 'timestamp'], array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertTrue($table->hasIndex('i_description'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        $table
            ->ensureColumn(new rex_sql_column('timestamp', 'datetime', true))
            ->ensureColumn(new rex_sql_column('id', 'int(11)', false, null, 'auto_increment'))
            ->ensureColumn(new rex_sql_column('status', 'tinyint(1)'))
            ->ensureColumn(new rex_sql_column('title', 'varchar(20)', false), 'timestamp')
            ->ensureColumn(new rex_sql_column('teaser', 'varchar(255)', false), 'status')
            ->setPrimaryKey(['id', 'title'])
            ->ensureIndex(new rex_sql_index('i_status_timestamp', ['status', 'timestamp'], rex_sql_index::UNIQUE))
            ->ensure();

        $expectedOrder = ['timestamp', 'title', 'id', 'status', 'teaser', 'description'];

        self::assertSame($expectedOrder, array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertSame(rex_sql_index::UNIQUE, $table->getIndex('i_status_timestamp')?->getType());
        self::assertTrue($table->hasIndex('i_description'));

        rex_sql_table::clearInstance(self::TABLE);
        $table = rex_sql_table::get(self::TABLE);

        self::assertSame(['title', 'id'], $table->getPrimaryKey());
        self::assertTrue($table->hasColumn('description'));
        self::assertNull($table->getColumn('title')->getDefault());
        self::assertSame($expectedOrder, array_keys($table->getColumns()));
        self::assertTrue($table->hasIndex('i_status_timestamp'));
        self::assertSame(rex_sql_index::UNIQUE, $table->getIndex('i_status_timestamp')?->getType());
        self::assertTrue($table->hasIndex('i_description'));
    }

    #[DoesNotPerformAssertions]
    public function testEnsureMultipleTimes(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            rex_sql_table::get(self::TABLE)
                ->ensurePrimaryIdColumn()
                ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
                ->ensure();
        }
    }

    #[DataProvider('provideIntegerTypes')]
    public function testEnsureIntegerColumn(string $type): void
    {
        $column = new rex_sql_column('foo', $type, true);

        rex_sql_table::get(self::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn($column)
            ->ensure();

        rex_sql_table::clearInstance(self::TABLE);

        self::assertEquals($column, rex_sql_table::get(self::TABLE)->getColumn('foo'));
    }

    /** @return iterable<string, array{string}> */
    public static function provideIntegerTypes(): iterable
    {
        $types = [
            'tinyint(4)', 'tinyint(3) unsigned', 'tinyint(1)',
            'smallint(6)', 'smallint(5) unsigned',
            'mediumint(9)', 'mediumint(8) unsigned',
            'int(11)', 'int(10) unsigned',
            'bigint(20)', 'bigint(20) unsigned',
        ];

        foreach ($types as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('provideCurrentTimestampColumns')]
    public function testEnsureCurrentTimestampColumn(rex_sql_column $column, string $expectedDefault, ?string $expectedExtra): void
    {
        rex_sql_table::get(self::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn($column)
            ->ensure();

        rex_sql_table::clearInstance(self::TABLE);

        $readColumn = rex_sql_table::get(self::TABLE)->getColumn('foo');
        self::assertInstanceOf(rex_sql_column::class, $readColumn);

        self::assertSame($expectedDefault, $readColumn->getDefault());
        self::assertSame($expectedExtra, $readColumn->getExtra());
        self::assertTrue($column->equals($readColumn));
    }

    /** @return iterable<string, array{rex_sql_column, string, string|null}> */
    public static function provideCurrentTimestampColumns(): iterable
    {
        yield 'datetime' => [
            new rex_sql_column('foo', 'datetime', false, 'CURRENT_TIMESTAMP'),
            'CURRENT_TIMESTAMP', null,
        ];
        yield 'datetime, function spelling' => [
            new rex_sql_column('foo', 'datetime', false, 'current_timestamp()'),
            'CURRENT_TIMESTAMP', null,
        ];
        yield 'datetime with precision' => [
            new rex_sql_column('foo', 'datetime(3)', false, 'current_timestamp(3)'),
            'CURRENT_TIMESTAMP(3)', null,
        ];
        yield 'timestamp, on update' => [
            new rex_sql_column('foo', 'timestamp', false, 'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'),
            'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP',
        ];
        yield 'timestamp, on update function spelling' => [
            new rex_sql_column('foo', 'timestamp', false, 'current_timestamp()', 'on update current_timestamp()'),
            'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP',
        ];
    }

    public function testEnsureWithEnsureGlobalColumns(): void
    {
        $expectedOrder = ['id', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser', 'revision'];

        for ($i = 1; $i <= 2; ++$i) {
            $table = rex_sql_table::get(self::TABLE);
            $table
                ->ensurePrimaryIdColumn()
                ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
                ->ensureGlobalColumns()
                ->ensureColumn(new rex_sql_column('revision', 'tinyint(1)'))
                ->ensure();

            rex_sql_table::clearInstance(self::TABLE);
            $table = rex_sql_table::get(self::TABLE);

            self::assertSame($expectedOrder, array_keys($table->getColumns()), "Column order does not match expected order (\$i = $i)");
        }
    }

    public function testRenameNonExistingTable(): void
    {
        $this->expectException(rex_exception::class);
        $this->expectExceptionMessage('Table "rex_non_existing" does not exist.');

        rex_sql_table::get('rex_non_existing')
            ->setName('rex_foo')
            ->alter();
    }

    public function testClearInstance(): void
    {
        $table = rex_sql_table::get(self::TABLE);

        rex_sql_table::clearInstance(self::TABLE);
        $table2 = rex_sql_table::get(self::TABLE);

        self::assertNotSame($table2, $table);

        rex_sql_table::clearInstance([1, self::TABLE]);
        $table3 = rex_sql_table::get(self::TABLE);

        self::assertNotSame($table3, $table);
        self::assertNotSame($table3, $table2);
    }
}
