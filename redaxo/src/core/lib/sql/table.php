<?php

/**
 * Class to represent sql tables.
 *
 * To persist changes, choose the right method:
 * - {@see ensure()} when the object describes the complete table (create-or-migrate, enforces column order).
 * - {@see alter()} for incremental changes to an existing table (e.g. just a few `ensureColumn()` calls).
 *
 * @author gharlan
 *
 * @package redaxo\core\sql
 */
class rex_sql_table
{
    use rex_instance_pool_trait {
        clearInstance as private baseClearInstance;
    }

    public const FIRST = 'FIRST '; // The space is intended: column names cannot end with space

    /** @var array<string, array{int, int}> Default display width of integer types, signed and unsigned */
    private const INT_DISPLAY_WIDTHS = [
        'tinyint' => [4, 3],
        'smallint' => [6, 5],
        'mediumint' => [9, 8],
        'int' => [11, 10],
        'bigint' => [20, 20],
    ];

    private int $db;
    private rex_sql $sql;
    private bool $new;
    private string $name;
    private string $originalName;

    /** @var array<string, rex_sql_column> */
    private array $columns = [];

    /** @var array<string, string> mapping from current (new) name to existing (old) name in database */
    private array $columnsExisting = [];

    /** @var list<string> */
    private array $implicitOrder = [];

    /** @var array<string, string> */
    private array $positions = [];

    /** @var list<string> */
    private array $primaryKey = [];

    /** @var list<string> */
    private array $primaryKeyExisting = [];

    /** @var array<string, rex_sql_index> */
    private array $indexes = [];

    /** @var array<string, string> mapping from current (new) name to existing (old) name in database */
    private array $indexesExisting = [];

    /** @var array<string, rex_sql_foreign_key> */
    private array $foreignKeys = [];

    /** @var array<string, string> mapping from current (new) name to existing (old) name in database */
    private array $foreignKeysExisting = [];

    private static ?string $explicitCharset = null;

    /** @param positive-int $db */
    private function __construct(string $name, int $db = 1)
    {
        $this->db = $db;
        $this->sql = rex_sql::factory($db);
        $this->name = $name;
        $this->originalName = $name;

        try {
            $columns = rex_sql::showColumns($name, $db);
            $this->new = false;
        } catch (rex_sql_exception $exception) {
            $sql = $exception->getSql();
            if ($sql && rex_sql::ERRNO_TABLE_OR_VIEW_DOESNT_EXIST !== $sql->getErrno()) {
                throw $exception;
            }

            $this->new = true;

            return;
        }

        foreach ($columns as $column) {
            $type = $column['type'];

            // Since MySQL 8.0.17 the display width for integer columns is deprecated and it is not
            // reported anymore. To be compatible with our code for MySQL 5 and MariaDB we simulate
            // the max display width. `tinyint(1)` and zerofill columns still report their width.
            // https://dev.mysql.com/doc/refman/8.0/en/numeric-type-attributes.html
            if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)( unsigned)?$/', $type, $match)) {
                $unsigned = $match[2] ?? '';
                [$signedWidth, $unsignedWidth] = self::INT_DISPLAY_WIDTHS[$match[1]];
                $type = $match[1] . '(' . ('' === $unsigned ? $signedWidth : $unsignedWidth) . ')' . $unsigned;
            }

            $default = $column['default'];
            if ("''" === $default && in_array($type, ['tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
                $default = '';
            }

            $this->columns[$column['name']] = new rex_sql_column(
                $column['name'],
                $type,
                'YES' === $column['null'],
                rex_sql_column::normalizeDefault($type, $default),
                rex_sql_column::normalizeExtra($column['extra'] ?: null),
                $column['comment'] ?: null,
            );

            $this->columnsExisting[$column['name']] = $column['name'];

            if ('PRI' === $column['key']) {
                $this->primaryKey[] = $column['name'];
            }
        }

        $this->primaryKeyExisting = $this->primaryKey;

        /** @var list<array<string, string>> $indexParts */
        $indexParts = $this->sql->getArray('SHOW INDEXES FROM ' . $this->sql->escapeIdentifier($name));
        $indexes = [];
        foreach ($indexParts as $part) {
            if ('PRIMARY' !== $part['Key_name']) {
                $indexes[$part['Key_name']][] = $part;
            }
        }

        foreach ($indexes as $indexName => $parts) {
            $columns = [];
            foreach ($parts as $part) {
                $columns[] = $part['Column_name'];
            }

            if ('FULLTEXT' === $parts[0]['Index_type']) {
                $type = rex_sql_index::FULLTEXT;
            } elseif (0 === (int) $parts[0]['Non_unique']) {
                $type = rex_sql_index::UNIQUE;
            } else {
                $type = rex_sql_index::INDEX;
            }

            $this->indexes[$indexName] = new rex_sql_index($indexName, $columns, $type);
            $this->indexesExisting[$indexName] = $indexName;
        }

        // KEY_COLUMN_USAGE spans all schemas and also lists unique/primary keys, so the join must be qualified.
        // INFORMATION_SCHEMA gives no ordering guarantee, and the column order of a composite key ends up in the DDL.
        /** @var list<array{CONSTRAINT_NAME: string, COLUMN_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string, UPDATE_RULE: rex_sql_foreign_key::*, DELETE_RULE: rex_sql_foreign_key::*}> $foreignKeyParts */
        $foreignKeyParts = $this->sql->getArray('
            SELECT c.CONSTRAINT_NAME, c.REFERENCED_TABLE_NAME, c.UPDATE_RULE, c.DELETE_RULE, k.COLUMN_NAME, k.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS c
            INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                ON c.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                AND c.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                AND c.TABLE_NAME = k.TABLE_NAME
                AND k.POSITION_IN_UNIQUE_CONSTRAINT IS NOT NULL
            WHERE c.CONSTRAINT_SCHEMA = DATABASE() AND c.TABLE_NAME = ?
            ORDER BY c.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$name]);
        $foreignKeys = [];
        foreach ($foreignKeyParts as $part) {
            $foreignKeys[$part['CONSTRAINT_NAME']][] = $part;
        }

        foreach ($foreignKeys as $fkName => $parts) {
            $columns = [];
            foreach ($parts as $part) {
                $columns[$part['COLUMN_NAME']] = $part['REFERENCED_COLUMN_NAME'];
            }

            $fk = $parts[0];

            $this->foreignKeys[$fkName] = new rex_sql_foreign_key($fkName, $fk['REFERENCED_TABLE_NAME'], $columns, $fk['UPDATE_RULE'], $fk['DELETE_RULE']);
            $this->foreignKeysExisting[$fkName] = $fkName;
        }
    }

    /**
     * @param non-empty-string $name
     * @param positive-int $db
     *
     * @return self
     */
    public static function get($name, int $db = 1)
    {
        $table = static::getInstance(
            [$db, $name],
            /** @param positive-int $db */
            static fn (int $db, string $name) => new static($name, $db),
        );

        return rex_type::instanceOf($table, self::class);
    }

    /**
     * @param string|array{int, string} $key A table-name or a array[db-id, table-name]
     * @return void
     */
    public static function clearInstance($key)
    {
        // BC layer for old cache keys without db id
        if (!is_array($key)) {
            $key = [1, $key];
        }

        self::baseClearInstance($key);
    }

    /** @return bool */
    public function exists()
    {
        return !$this->new;
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasColumn($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * @param string $name
     *
     * @return rex_sql_column|null
     */
    public function getColumn($name)
    {
        if (!$this->hasColumn($name)) {
            return null;
        }

        return $this->columns[$name];
    }

    /** @return array<string, rex_sql_column> */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param string|null $afterColumn Column name or `rex_sql_table::FIRST`
     *
     * @return $this
     */
    public function addColumn(rex_sql_column $column, $afterColumn = null)
    {
        $name = $column->getName();

        if ($this->hasColumn($name)) {
            throw new RuntimeException(sprintf('Column "%s" already exists.', $name));
        }

        $this->columns[$name] = $column;

        $this->setPosition($name, $afterColumn);

        return $this;
    }

    /**
     * @param string|null $afterColumn Column name or `rex_sql_table::FIRST`
     *
     * @return $this
     */
    public function ensureColumn(rex_sql_column $column, $afterColumn = null)
    {
        $name = $column->getName();
        $existing = $this->getColumn($name);

        if (!$existing) {
            return $this->addColumn($column, $afterColumn);
        }

        $this->setPosition($name, $afterColumn);

        if ($existing->equals($column)) {
            return $this;
        }

        $this->columns[$name] = $column->setModified(true);

        return $this;
    }

    /** @return $this */
    public function ensurePrimaryIdColumn()
    {
        return $this
            ->ensureColumn(new rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
            ->setPrimaryKey('id')
        ;
    }

    /**
     * @param string|null $afterColumn Column name or `rex_sql_table::FIRST`
     *
     * @return $this
     */
    public function ensureGlobalColumns($afterColumn = null)
    {
        return $this
            ->ensureColumn(new rex_sql_column('createdate', 'datetime'), $afterColumn)
            ->ensureColumn(new rex_sql_column('createuser', 'varchar(255)'), 'createdate')
            ->ensureColumn(new rex_sql_column('updatedate', 'datetime'), 'createuser')
            ->ensureColumn(new rex_sql_column('updateuser', 'varchar(255)'), 'updatedate')
        ;
    }

    /**
     * @param string $oldName
     * @param string $newName
     *
     * @throws rex_exception
     *
     * @return $this
     */
    public function renameColumn($oldName, $newName)
    {
        $column = $this->getColumn($oldName);
        if (!$column) {
            throw new rex_exception(sprintf('Column with name "%s" does not exist.', $oldName));
        }

        if ($this->hasColumn($newName)) {
            throw new rex_exception(sprintf('Column with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        $column->setName($newName);

        unset($this->columns[$oldName]);
        $this->columns[$newName] = $column;

        if (isset($this->columnsExisting[$oldName])) {
            $this->columnsExisting[$newName] = $this->columnsExisting[$oldName];
            unset($this->columnsExisting[$oldName]);
        }

        if (false !== $key = array_search($oldName, $this->primaryKey)) {
            /** @psalm-suppress PropertyTypeCoercion */
            $this->primaryKey[$key] = $newName;
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function removeColumn($name)
    {
        unset($this->columns[$name]);

        return $this;
    }

    /** @return non-empty-list<string>|null Column names */
    public function getPrimaryKey()
    {
        return $this->primaryKey ?: null;
    }

    /**
     * @param string|list<string>|null $columns Column name(s)
     *
     * @throws rex_exception
     *
     * @return $this
     */
    public function setPrimaryKey($columns)
    {
        if (is_array($columns) && !$columns) {
            throw new rex_exception('The primary key column array can not be empty. To delete the primary key use `null` instead.');
        }

        $columns = null === $columns ? [] : (array) $columns;

        if ($this->primaryKey === $columns) {
            return $this;
        }

        $this->primaryKey = $columns;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasIndex($name)
    {
        return isset($this->indexes[$name]);
    }

    /**
     * @param string $name
     *
     * @return rex_sql_index|null
     */
    public function getIndex($name)
    {
        if (!$this->hasIndex($name)) {
            return null;
        }

        return $this->indexes[$name];
    }

    /** @return array<string, rex_sql_index> */
    public function getIndexes()
    {
        return $this->indexes;
    }

    /** @return $this */
    public function addIndex(rex_sql_index $index)
    {
        $name = $index->getName();

        if ($this->hasIndex($name)) {
            throw new RuntimeException(sprintf('Index "%s" already exists.', $name));
        }

        $this->indexes[$name] = $index;

        return $this;
    }

    /** @return $this */
    public function ensureIndex(rex_sql_index $index)
    {
        $name = $index->getName();
        $existing = $this->getIndex($name);

        if (!$existing) {
            return $this->addIndex($index);
        }

        if ($existing->equals($index)) {
            return $this;
        }

        $this->indexes[$name] = $index->setModified(true);

        return $this;
    }

    /**
     * @param string $oldName
     * @param string $newName
     *
     * @throws rex_exception
     *
     * @return $this
     */
    public function renameIndex($oldName, $newName)
    {
        $index = $this->getIndex($oldName);
        if (!$index) {
            throw new rex_exception(sprintf('Index with name "%s" does not exist.', $oldName));
        }

        if ($this->hasIndex($newName)) {
            throw new rex_exception(sprintf('Index with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        $index->setName($newName);

        unset($this->indexes[$oldName]);
        $this->indexes[$newName] = $index;

        if (isset($this->indexesExisting[$oldName])) {
            $this->indexesExisting[$newName] = $this->indexesExisting[$oldName];
            unset($this->indexesExisting[$oldName]);
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function removeIndex($name)
    {
        unset($this->indexes[$name]);

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasForeignKey($name)
    {
        return isset($this->foreignKeys[$name]);
    }

    /**
     * @param string $name
     *
     * @return rex_sql_foreign_key|null
     */
    public function getForeignKey($name)
    {
        if (!$this->hasForeignKey($name)) {
            return null;
        }

        return $this->foreignKeys[$name];
    }

    /** @return array<string, rex_sql_foreign_key> */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    /** @return $this */
    public function addForeignKey(rex_sql_foreign_key $foreignKey)
    {
        $name = $foreignKey->getName();

        if ($this->hasForeignKey($name)) {
            throw new RuntimeException(sprintf('Foreign key "%s" already exists.', $name));
        }

        $this->foreignKeys[$name] = $foreignKey;

        return $this;
    }

    /** @return $this */
    public function ensureForeignKey(rex_sql_foreign_key $foreignKey)
    {
        $name = $foreignKey->getName();
        $existing = $this->getForeignKey($name);

        if (!$existing) {
            return $this->addForeignKey($foreignKey);
        }

        if ($existing->equals($foreignKey)) {
            return $this;
        }

        $this->foreignKeys[$name] = $foreignKey->setModified(true);

        return $this;
    }

    /**
     * @param string $oldName
     * @param string $newName
     *
     * @throws rex_exception
     *
     * @return $this
     */
    public function renameForeignKey($oldName, $newName)
    {
        $foreignKey = $this->getForeignKey($oldName);
        if (!$foreignKey) {
            throw new rex_exception(sprintf('Foreign key with name "%s" does not exist.', $oldName));
        }

        if ($this->hasForeignKey($newName)) {
            throw new rex_exception(sprintf('Foreign key with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        $foreignKey->setName($newName);

        unset($this->foreignKeys[$oldName]);
        $this->foreignKeys[$newName] = $foreignKey;

        if (isset($this->foreignKeysExisting[$oldName])) {
            $this->foreignKeysExisting[$newName] = $this->foreignKeysExisting[$oldName];
            unset($this->foreignKeysExisting[$oldName]);
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function removeForeignKey($name)
    {
        unset($this->foreignKeys[$name]);

        return $this;
    }

    /**
     * Ensures that the table exists with the given definition.
     *
     * Use this only when the object describes the **complete** desired table: a non-existing table is created,
     * an existing one is migrated to match. This also enforces the column order based on the order in which the
     * columns were added/ensured, so existing columns may be reordered to fit.
     *
     * Note: columns are never dropped implicitly just because they are missing from the definition — a column is
     * only dropped when you explicitly call {@see removeColumn()}. Same for indexes and foreign keys.
     *
     * Do **not** use this when you only added/changed individual columns of an existing table (e.g. a few
     * `ensureColumn()` calls) without describing the full table — that would reorder the existing columns.
     * Use {@see alter()} for such incremental changes instead.
     *
     * @return void
     */
    public function ensure()
    {
        if ($this->new) {
            $this->create();

            return;
        }

        if (self::$explicitCharset) {
            $this->sql->setQuery('ALTER TABLE ' . $this->sql->escapeIdentifier($this->originalName) . ' CONVERT TO CHARACTER SET ' . self::$explicitCharset . ' COLLATE ' . self::$explicitCharset . '_unicode_ci;');
        }

        $positions = $this->positions;
        $this->positions = [];

        $previous = self::FIRST;
        foreach ($this->implicitOrder as $name) {
            if (isset($this->positions[$name])) {
                continue;
            }

            $this->positions[$name] = $previous;
            $previous = $name;
        }

        $implicitReversedPositions = array_flip($this->positions);

        foreach ($positions as $name => $after) {
            // unset is necessary to add new position as last array element
            unset($this->positions[$name]);
            $this->positions[$name] = $after;

            if (isset($implicitReversedPositions[$after])) {
                // move the implicitly after `$after` positioned column
                // after the one that was explicitly positioned at that position
                $this->positions[$implicitReversedPositions[$after]] = $name;
                $implicitReversedPositions[$name] = $implicitReversedPositions[$after];
                unset($implicitReversedPositions[$after]);
            }
        }

        $this->alter();
    }

    /**
     * Drops the table if it exists.
     * @return void
     */
    public function drop()
    {
        if (!$this->new) {
            $this->sql->setQuery(sprintf('DROP TABLE %s', $this->sql->escapeIdentifier($this->name)));
        }

        $this->new = true;
        $this->originalName = $this->name;
        $this->columnsExisting = [];
        $this->implicitOrder = [];
        $this->positions = [];
        $this->primaryKeyExisting = [];
    }

    /**
     * Creates the table.
     *
     * @throws rex_exception
     * @return void
     */
    public function create()
    {
        if (!$this->new) {
            throw new rex_exception(sprintf('Table "%s" already exists.', $this->name));
        }
        if (!$this->columns) {
            throw new rex_exception('A table must have at least one column.');
        }

        $this->sortColumns();

        $parts = [];

        foreach ($this->columns as $column) {
            $parts[] = $this->getColumnDefinition($column);
        }

        if ($this->primaryKey) {
            $parts[] = 'PRIMARY KEY ' . $this->getKeyColumnsDefintion($this->primaryKey);
        }

        foreach ($this->indexes as $index) {
            $parts[] = $this->getIndexDefinition($index);
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $parts[] = $this->getForeignKeyDefinition($foreignKey);
        }

        $charset = self::$explicitCharset ?? (rex::getConfig('utf8mb4') ? 'utf8mb4' : 'utf8');

        $query = 'CREATE TABLE ' . $this->sql->escapeIdentifier($this->name) . " (\n    ";
        $query .= implode(",\n    ", $parts);
        $query .= "\n) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE={$charset}_unicode_ci;";

        $this->sql->setQuery($query);

        $this->resetModified();
    }

    /**
     * Applies the pending changes to an existing table.
     *
     * Use this for **incremental** modifications of an existing table (add/change/drop individual columns,
     * indexes or foreign keys, rename the table). Only the changes you explicitly made are applied; untouched
     * columns keep their current position. Newly added columns without an explicit position are appended at the end.
     *
     * Unlike {@see ensure()}, this does **not** enforce the full column order, which is exactly what you want when
     * you did not describe the complete table. The table must already exist (otherwise a `rex_exception` is thrown);
     * to create-or-migrate from a full definition use {@see ensure()}.
     *
     * @throws rex_exception
     * @return void
     */
    public function alter()
    {
        if ($this->new) {
            throw new rex_exception(sprintf('Table "%s" does not exist.', $this->originalName));
        }

        $parts = [];
        $dropForeignKeys = [];

        if ($this->name !== $this->originalName) {
            $parts[] = 'RENAME ' . $this->sql->escapeIdentifier($this->name);
        }

        if ($this->primaryKeyExisting && $this->primaryKeyExisting !== $this->primaryKey) {
            $parts[] = 'DROP PRIMARY KEY';
        }

        foreach ($this->indexesExisting as $newName => $oldName) {
            if (!isset($this->indexes[$newName]) || $this->indexes[$newName]->isModified()) {
                $parts[] = 'DROP INDEX ' . $this->sql->escapeIdentifier($oldName);
            }
        }

        foreach ($this->foreignKeysExisting as $newName => $oldName) {
            if (!isset($this->foreignKeys[$newName]) || $this->foreignKeys[$newName]->isModified()) {
                $dropForeignKeys[] = 'DROP FOREIGN KEY ' . $this->sql->escapeIdentifier($oldName);
            }
        }

        $columns = $this->columns;
        $columnsExisting = $this->columnsExisting;

        $handle = function (string $name, ?string $after = null) use (&$parts, &$columns, &$columnsExisting) {
            $column = $columns[$name];
            $new = !isset($columnsExisting[$name]);
            $oldName = $new ? null : $columnsExisting[$name];
            unset($columns[$name], $columnsExisting[$name]);

            if (!$new && !$column->isModified() && null === $after) {
                return;
            }

            $definition = $this->getColumnDefinition($column);

            if (self::FIRST === $after) {
                $definition .= ' FIRST';
            } elseif (null !== $after) {
                $definition .= ' AFTER ' . $this->sql->escapeIdentifier($after);
            }

            if ($new) {
                $parts[] = 'ADD ' . $definition;
            } else {
                $parts[] = 'CHANGE ' . $this->sql->escapeIdentifier($oldName) . ' ' . $definition;
            }
        };

        $currentOrder = [];
        $after = self::FIRST;
        /** @var string $name */
        foreach ($columns as $name => $_) {
            $currentOrder[$after] = $name;
            $after = $name;

            if (!isset($this->positions[$name])) {
                $handle($name);
            }
        }

        foreach ($this->positions as $name => $after) {
            if (!isset($columns[$name])) {
                continue;
            }

            if (isset($currentOrder[$after]) && $currentOrder[$after] === $name) {
                $after = null;
            } else {
                unset($currentOrder[$name]);
            }

            $handle($name, $after);
        }

        foreach ($columnsExisting as $oldName) {
            $parts[] = 'DROP ' . $this->sql->escapeIdentifier($oldName);
        }

        if ($this->primaryKey && $this->primaryKey !== $this->primaryKeyExisting) {
            $parts[] = 'ADD PRIMARY KEY ' . $this->getKeyColumnsDefintion($this->primaryKey);
        }

        $fulltextIndexes = [];
        $fulltextAdded = false;
        foreach ($this->indexes as $index) {
            if (!$index->isModified() && isset($this->indexesExisting[$index->getName()])) {
                continue;
            }

            if (rex_sql_index::FULLTEXT === $index->getType()) {
                if ($fulltextAdded) {
                    $fulltextIndexes[] = 'ADD ' . $this->getIndexDefinition($index);

                    continue;
                }

                $fulltextAdded = true;
            }

            $parts[] = 'ADD ' . $this->getIndexDefinition($index);
        }

        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey->isModified() || !isset($this->foreignKeysExisting[$foreignKey->getName()])) {
                $parts[] = 'ADD ' . $this->getForeignKeyDefinition($foreignKey);
            }
        }

        if (!$parts && !$dropForeignKeys) {
            $this->resetModified();

            return;
        }

        foreach ([$dropForeignKeys, $parts] as $stepParts) {
            if ($stepParts) {
                $query = 'ALTER TABLE ' . $this->sql->escapeIdentifier($this->originalName) . "\n    ";
                $query .= implode(",\n    ", $stepParts);
                $query .= ';';

                $this->sql->setQuery($query);
            }
        }

        foreach ($fulltextIndexes as $fulltextIndex) {
            $this->sql->setQuery('ALTER TABLE ' . $this->sql->escapeIdentifier($this->originalName) . ' ' . $fulltextIndex . ';');
        }

        $this->sortColumns();
        $this->resetModified();
    }

    private function setPosition(string $name, ?string $afterColumn): void
    {
        if (null === $afterColumn) {
            $this->implicitOrder[] = $name;

            return;
        }

        if (self::FIRST !== $afterColumn && !$this->hasColumn($afterColumn)) {
            throw new InvalidArgumentException(sprintf('Column "%s" can not be placed after "%s", because that column does not exist.', $name, $afterColumn));
        }

        // unset is necessary to add new position as last array element
        unset($this->positions[$name]);
        $this->positions[$name] = $afterColumn;
    }

    private function getColumnDefinition(rex_sql_column $column): string
    {
        $default = $column->getDefault();
        if (null === $default) {
            $default = '';
        } elseif ($column->hasCurrentTimestampDefault()) {
            $default = 'DEFAULT ' . $default;
        } else {
            $default = 'DEFAULT ' . $this->sql->escape($default);
        }

        $comment = $column->getComment() ?? '';
        if ('' !== $comment) {
            $comment = 'COMMENT ' . $this->sql->escape($comment);
        }

        return sprintf(
            '%s %s %s %s %s %s',
            $this->sql->escapeIdentifier($column->getName()),
            $column->getType(),
            $default,
            $column->isNullable() ? '' : 'NOT NULL',
            $column->getExtra() ?? '',
            $comment,
        );
    }

    private function getIndexDefinition(rex_sql_index $index): string
    {
        return sprintf(
            '%s %s %s',
            $index->getType(),
            $this->sql->escapeIdentifier($index->getName()),
            $this->getKeyColumnsDefintion($index->getColumns()),
        );
    }

    private function getForeignKeyDefinition(rex_sql_foreign_key $foreignKey): string
    {
        return sprintf(
            'CONSTRAINT %s FOREIGN KEY %s REFERENCES %s %s ON UPDATE %s ON DELETE %s',
            $this->sql->escapeIdentifier($foreignKey->getName()),
            $this->getKeyColumnsDefintion(array_keys($foreignKey->getColumns())),
            $this->sql->escapeIdentifier($foreignKey->getTable()),
            $this->getKeyColumnsDefintion($foreignKey->getColumns()),
            $foreignKey->getOnUpdate(),
            $foreignKey->getOnDelete(),
        );
    }

    private function getKeyColumnsDefintion(array $columns): string
    {
        $columns = array_map($this->sql->escapeIdentifier(...), $columns);

        return '(' . implode(', ', $columns) . ')';
    }

    private function sortColumns(): void
    {
        $columns = [];

        foreach ($this->columns as $name => $column) {
            if (!isset($this->positions[$name])) {
                $columns[$name] = $column;
            }
        }

        while ($count = count($this->positions)) {
            foreach ($this->positions as $name => $after) {
                $insert = [$name => $this->columns[$name]];

                if (self::FIRST === $after) {
                    $columns = $insert + $columns;
                    unset($this->positions[$name]);

                    continue;
                }

                if (!isset($columns[$after])) {
                    continue;
                }

                $offset = rex_type::int(array_search($after, array_keys($columns)));
                ++$offset;
                $columns = array_slice($columns, 0, $offset) + $insert + array_slice($columns, $offset);
                unset($this->positions[$name]);
            }

            if ($count === count($this->positions)) {
                throw new LogicException('Columns can not be sorted because some explicit positions do not exist.');
            }
        }

        $this->columns = $columns;
    }

    private function resetModified(): void
    {
        $this->new = false;

        if ($this->originalName !== $this->name) {
            self::clearInstance([$this->db, $this->originalName]);
            self::addInstance([$this->db, $this->name], $this);
        }

        $this->originalName = $this->name;

        $columns = $this->columns;
        $this->columns = [];
        $this->columnsExisting = [];
        foreach ($columns as $column) {
            $column->setModified(false);
            $this->columns[$column->getName()] = $column;
            $this->columnsExisting[$column->getName()] = $column->getName();
        }

        $this->implicitOrder = [];
        $this->positions = [];

        $this->primaryKeyExisting = $this->primaryKey;

        $indexes = $this->indexes;
        $this->indexes = [];
        $this->indexesExisting = [];
        foreach ($indexes as $index) {
            $index->setModified(false);
            $this->indexes[$index->getName()] = $index;
            $this->indexesExisting[$index->getName()] = $index->getName();
        }

        $foreignKeys = $this->foreignKeys;
        $this->foreignKeys = [];
        $this->foreignKeysExisting = [];
        foreach ($foreignKeys as $foreignKey) {
            $foreignKey->setModified(false);
            $this->foreignKeys[$foreignKey->getName()] = $foreignKey;
            $this->foreignKeysExisting[$foreignKey->getName()] = $foreignKey->getName();
        }
    }

    /**
     * Method is used in redaxo setup and should not be used anywhere else.
     *
     * @internal
     */
    public static function setUtf8mb4(bool $utf8mb4): void
    {
        self::$explicitCharset = $utf8mb4 ? 'utf8mb4' : 'utf8';
    }
}
