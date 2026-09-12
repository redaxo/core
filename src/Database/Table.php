<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Util\Type;

use function array_slice;
use function count;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Class to represent sql tables.
 *
 * To persist changes, choose the right method:
 * - {@see ensure()} when the object describes the complete table (create-or-migrate, enforces column order).
 * - {@see alter()} for incremental changes to an existing table (e.g. just a few `ensureColumn()` calls).
 */
final class Table
{
    use InstancePoolTrait {
        clearInstance as private baseClearInstance;
    }

    public const string FIRST = 'FIRST '; // The space is intended: column names cannot end with space

    private readonly int $db;
    private readonly Sql $sql;
    private bool $new;
    private string $name;
    private string $originalName;

    /** @var array<string, Column> */
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

    /** @var array<string, Index> */
    private array $indexes = [];

    /** @var array<string, string> mapping from current (new) name to existing (old) name in database */
    private array $indexesExisting = [];

    /** @var array<string, ForeignKey> */
    private array $foreignKeys = [];

    /** @var array<string, string> mapping from current (new) name to existing (old) name in database */
    private array $foreignKeysExisting = [];

    /** @var array<string, true> names of the columns, indexes and foreign keys changed since the last write */
    private array $modifiedColumns = [];

    /** @var array<string, true> */
    private array $modifiedIndexes = [];

    /** @var array<string, true> */
    private array $modifiedForeignKeys = [];

    /** @param positive-int $db */
    private function __construct(string $name, int $db = 1)
    {
        $this->db = $db;
        $this->sql = Sql::factory($db);
        $this->name = $name;
        $this->originalName = $name;

        try {
            $columns = Sql::showColumns($name, $db);
            $this->new = false;
        } catch (SqlException $exception) {
            if (Sql::ERRNO_TABLE_OR_VIEW_DOESNT_EXIST !== $exception->sql?->getErrno()) {
                throw $exception;
            }

            $this->new = true;

            return;
        }

        foreach ($columns as $column) {
            $type = Column::normalizeType($column['type']);

            $default = $column['default'];
            if ("''" === $default && in_array($type, ['tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
                $default = '';
            }

            $this->columns[$column['name']] = new Column(
                $column['name'],
                $type,
                'YES' === $column['null'],
                Column::normalizeDefault($type, $default),
                Column::normalizeExtra($column['extra'] ?: null),
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
                $type = Index::FULLTEXT;
            } elseif (0 === (int) $parts[0]['Non_unique']) {
                $type = Index::UNIQUE;
            } else {
                $type = Index::INDEX;
            }

            $this->indexes[$indexName] = new Index($indexName, $columns, $type);
            $this->indexesExisting[$indexName] = $indexName;
        }

        // KEY_COLUMN_USAGE spans all schemas and also lists unique/primary keys, so the join must be qualified.
        // INFORMATION_SCHEMA gives no ordering guarantee, and the column order of a composite key ends up in the DDL.
        /** @var list<array{CONSTRAINT_NAME: string, COLUMN_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string, UPDATE_RULE: ForeignKey::*, DELETE_RULE: ForeignKey::*}> $foreignKeyParts */
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

            $this->foreignKeys[$fkName] = new ForeignKey($fkName, $fk['REFERENCED_TABLE_NAME'], $columns, $fk['UPDATE_RULE'], $fk['DELETE_RULE']);
            $this->foreignKeysExisting[$fkName] = $fkName;
        }
    }

    /**
     * @param non-empty-string $name
     * @param positive-int $db
     */
    public static function get(string $name, int $db = 1): self
    {
        $table = self::getInstance([$db, $name], static fn (): self => new self($name, $db));

        return Type::instanceOf($table, self::class);
    }

    public static function clearInstance(string $name, int $db = 1): void
    {
        self::baseClearInstance([$db, $name]);
    }

    public function exists(): bool
    {
        return !$this->new;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function getColumn(string $name): ?Column
    {
        if (!$this->hasColumn($name)) {
            return null;
        }

        return $this->columns[$name];
    }

    /** @return array<string, Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @param string|null $afterColumn Column name or `Table::FIRST` */
    public function addColumn(Column $column, ?string $afterColumn = null): self
    {
        $name = $column->name;

        if ($this->hasColumn($name)) {
            throw new RuntimeException(sprintf('Column "%s" already exists.', $name));
        }

        $this->columns[$name] = $column;

        $this->setPosition($name, $afterColumn);

        return $this;
    }

    /** @param string|null $afterColumn Column name or `Table::FIRST` */
    public function ensureColumn(Column $column, ?string $afterColumn = null): self
    {
        $name = $column->name;
        $existing = $this->getColumn($name);

        if (!$existing) {
            return $this->addColumn($column, $afterColumn);
        }

        $this->setPosition($name, $afterColumn);

        if ($existing->equals($column)) {
            return $this;
        }

        $this->columns[$name] = $column;
        $this->modifiedColumns[$name] = true;

        return $this;
    }

    public function ensurePrimaryIdColumn(): self
    {
        return $this
            ->ensureColumn(Column::int('id', unsigned: true, autoIncrement: true))
            ->setPrimaryKey('id')
        ;
    }

    /**
     * Ensures a column referencing the `id` column of another table, together with the foreign key.
     *
     * The column gets the same type as {@see ensurePrimaryIdColumn()} creates, which foreign keys
     * require: the referencing and referenced column must match exactly.
     *
     * `on update` defaults to `RESTRICT` because an auto increment id never changes, so a cascade
     * would only silently rewrite rows in the case that something went wrong.
     *
     * @param ForeignKey::RESTRICT|ForeignKey::NO_ACTION|ForeignKey::CASCADE|ForeignKey::SET_NULL $onUpdate
     * @param ForeignKey::RESTRICT|ForeignKey::NO_ACTION|ForeignKey::CASCADE|ForeignKey::SET_NULL $onDelete
     * @param string|null $afterColumn Column name or `Table::FIRST`
     */
    public function ensureForeignIdColumn(
        string $column,
        string $foreignTable,
        bool $nullable = false,
        string $onUpdate = ForeignKey::RESTRICT,
        string $onDelete = ForeignKey::RESTRICT,
        ?string $afterColumn = null,
    ): self {
        return $this
            ->ensureColumn(Column::int($column, unsigned: true, nullable: $nullable), $afterColumn)
            ->ensureForeignKeyTo($foreignTable, [$column => 'id'], $onUpdate, $onDelete)
        ;
    }

    /** @param string|null $afterColumn Column name or `Table::FIRST` */
    public function ensureGlobalColumns(?string $afterColumn = null): self
    {
        return $this
            ->ensureColumn(Column::datetime('createdate'), $afterColumn)
            ->ensureColumn(Column::varchar('createuser', 255), 'createdate')
            ->ensureColumn(Column::datetime('updatedate'), 'createuser')
            ->ensureColumn(Column::varchar('updateuser', 255), 'updatedate')
        ;
    }

    public function renameColumn(string $oldName, string $newName): self
    {
        $column = $this->getColumn($oldName);
        if (!$column) {
            throw new LogicException(sprintf('Column with name "%s" does not exist.', $oldName));
        }

        if ($this->hasColumn($newName)) {
            throw new LogicException(sprintf('Column with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        unset($this->columns[$oldName], $this->modifiedColumns[$oldName]);
        $this->columns[$newName] = $column->withName($newName);
        $this->modifiedColumns[$newName] = true;

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

    public function removeColumn(string $name): self
    {
        unset($this->columns[$name]);

        return $this;
    }

    /** @return non-empty-list<string>|null Column names */
    public function getPrimaryKey(): ?array
    {
        return $this->primaryKey ?: null;
    }

    /** @param string|list<string>|null $columns Column name(s) */
    public function setPrimaryKey(string|array|null $columns): self
    {
        if (is_array($columns) && !$columns) {
            throw new InvalidArgumentException('The primary key column array can not be empty. To delete the primary key use `null` instead.');
        }

        $columns = null === $columns ? [] : (array) $columns;

        if ($this->primaryKey === $columns) {
            return $this;
        }

        $this->primaryKey = $columns;

        return $this;
    }

    public function hasIndex(string $name): bool
    {
        return isset($this->indexes[$name]);
    }

    public function getIndex(string $name): ?Index
    {
        if (!$this->hasIndex($name)) {
            return null;
        }

        return $this->indexes[$name];
    }

    /** @return array<string, Index> */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function addIndex(Index $index): self
    {
        $name = $index->name;

        if ($this->hasIndex($name)) {
            throw new RuntimeException(sprintf('Index "%s" already exists.', $name));
        }

        $this->indexes[$name] = $index;

        return $this;
    }

    public function ensureIndex(Index $index): self
    {
        $name = $index->name;
        $existing = $this->getIndex($name);

        if (!$existing) {
            return $this->addIndex($index);
        }

        if ($existing->equals($index)) {
            return $this;
        }

        $this->indexes[$name] = $index;
        $this->modifiedIndexes[$name] = true;

        return $this;
    }

    public function renameIndex(string $oldName, string $newName): self
    {
        $index = $this->getIndex($oldName);
        if (!$index) {
            throw new LogicException(sprintf('Index with name "%s" does not exist.', $oldName));
        }

        if ($this->hasIndex($newName)) {
            throw new LogicException(sprintf('Index with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        unset($this->indexes[$oldName], $this->modifiedIndexes[$oldName]);
        $this->indexes[$newName] = $index->withName($newName);
        $this->modifiedIndexes[$newName] = true;

        if (isset($this->indexesExisting[$oldName])) {
            $this->indexesExisting[$newName] = $this->indexesExisting[$oldName];
            unset($this->indexesExisting[$oldName]);
        }

        return $this;
    }

    public function removeIndex(string $name): self
    {
        unset($this->indexes[$name]);

        return $this;
    }

    public function hasForeignKey(string $name): bool
    {
        return isset($this->foreignKeys[$name]);
    }

    public function getForeignKey(string $name): ?ForeignKey
    {
        if (!$this->hasForeignKey($name)) {
            return null;
        }

        return $this->foreignKeys[$name];
    }

    /** @return array<string, ForeignKey> */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function addForeignKey(ForeignKey $foreignKey): self
    {
        $name = $foreignKey->name;

        if ($this->hasForeignKey($name)) {
            throw new RuntimeException(sprintf('Foreign key "%s" already exists.', $name));
        }

        $this->foreignKeys[$name] = $foreignKey;

        return $this;
    }

    public function ensureForeignKey(ForeignKey $foreignKey): self
    {
        $name = $foreignKey->name;
        $existing = $this->getForeignKey($name);

        if (!$existing) {
            return $this->addForeignKey($foreignKey);
        }

        if ($existing->equals($foreignKey)) {
            return $this;
        }

        $this->foreignKeys[$name] = $foreignKey;
        $this->modifiedForeignKeys[$name] = true;

        return $this;
    }

    /**
     * Ensures a foreign key whose name follows the naming convention of this table.
     *
     * Unlike index names, foreign key names must be unique within the whole database, which is why
     * they are prefixed with the table name.
     *
     * @param array<string, string> $columns Mapping of local column to column in foreign table
     * @param ForeignKey::RESTRICT|ForeignKey::NO_ACTION|ForeignKey::CASCADE|ForeignKey::SET_NULL $onUpdate
     * @param ForeignKey::RESTRICT|ForeignKey::NO_ACTION|ForeignKey::CASCADE|ForeignKey::SET_NULL $onDelete
     */
    public function ensureForeignKeyTo(
        string $foreignTable,
        array $columns,
        string $onUpdate = ForeignKey::RESTRICT,
        string $onDelete = ForeignKey::RESTRICT,
    ): self {
        $name = $this->getForeignKeyName(array_keys($columns));

        return $this->ensureForeignKey(new ForeignKey($name, $foreignTable, $columns, $onUpdate, $onDelete));
    }

    /**
     * The conventional foreign key name for the given local columns.
     *
     * @param list<string> $columns
     *
     * @internal
     */
    public function getForeignKeyName(array $columns): string
    {
        return $this->name . '_' . implode('_', $columns);
    }

    public function renameForeignKey(string $oldName, string $newName): self
    {
        $foreignKey = $this->getForeignKey($oldName);
        if (!$foreignKey) {
            throw new LogicException(sprintf('Foreign key with name "%s" does not exist.', $oldName));
        }

        if ($this->hasForeignKey($newName)) {
            throw new LogicException(sprintf('Foreign key with the new name "%s" already exists.', $newName));
        }

        if ($oldName === $newName) {
            return $this;
        }

        unset($this->foreignKeys[$oldName], $this->modifiedForeignKeys[$oldName]);
        $this->foreignKeys[$newName] = $foreignKey->withName($newName);
        $this->modifiedForeignKeys[$newName] = true;

        if (isset($this->foreignKeysExisting[$oldName])) {
            $this->foreignKeysExisting[$newName] = $this->foreignKeysExisting[$oldName];
            unset($this->foreignKeysExisting[$oldName]);
        }

        return $this;
    }

    public function removeForeignKey(string $name): self
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
     */
    public function ensure(): void
    {
        if ($this->new) {
            $this->create();

            return;
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

    /** Drops the table if it exists. */
    public function drop(): void
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

    /** Creates the table. */
    public function create(): void
    {
        if (!$this->new) {
            throw new LogicException(sprintf('Table "%s" already exists.', $this->name));
        }
        if (!$this->columns) {
            throw new LogicException('A table must have at least one column.');
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

        $query = 'CREATE TABLE ' . $this->sql->escapeIdentifier($this->name) . " (\n    ";
        $query .= implode(",\n    ", $parts);
        $query .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

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
     */
    public function alter(): void
    {
        if ($this->new) {
            throw new LogicException(sprintf('Table "%s" does not exist.', $this->originalName));
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
            if (!isset($this->indexes[$newName]) || isset($this->modifiedIndexes[$newName])) {
                $parts[] = 'DROP INDEX ' . $this->sql->escapeIdentifier($oldName);
            }
        }

        foreach ($this->foreignKeysExisting as $newName => $oldName) {
            if (!isset($this->foreignKeys[$newName]) || isset($this->modifiedForeignKeys[$newName])) {
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

            if (!$new && !isset($this->modifiedColumns[$column->name]) && null === $after) {
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
            if (!isset($this->modifiedIndexes[$index->name]) && isset($this->indexesExisting[$index->name])) {
                continue;
            }

            if (Index::FULLTEXT === $index->type) {
                if ($fulltextAdded) {
                    $fulltextIndexes[] = 'ADD ' . $this->getIndexDefinition($index);

                    continue;
                }

                $fulltextAdded = true;
            }

            $parts[] = 'ADD ' . $this->getIndexDefinition($index);
        }

        foreach ($this->foreignKeys as $foreignKey) {
            if (isset($this->modifiedForeignKeys[$foreignKey->name]) || !isset($this->foreignKeysExisting[$foreignKey->name])) {
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

    private function getColumnDefinition(Column $column): string
    {
        $default = $column->default;
        if (null === $default) {
            $default = '';
        } elseif ($column->hasCurrentTimestampDefault()) {
            $default = 'DEFAULT ' . $default;
        } else {
            $default = 'DEFAULT ' . $this->sql->escape($default);
        }

        $comment = $column->comment ?? '';
        if ('' !== $comment) {
            $comment = 'COMMENT ' . $this->sql->escape($comment);
        }

        return sprintf(
            '%s %s %s %s %s %s',
            $this->sql->escapeIdentifier($column->name),
            $column->type,
            $default,
            $column->nullable ? '' : 'NOT NULL',
            $column->extra ?? '',
            $comment,
        );
    }

    private function getIndexDefinition(Index $index): string
    {
        return sprintf(
            '%s %s %s',
            $index->type,
            $this->sql->escapeIdentifier($index->name),
            $this->getKeyColumnsDefintion($index->columns),
        );
    }

    private function getForeignKeyDefinition(ForeignKey $foreignKey): string
    {
        return sprintf(
            'CONSTRAINT %s FOREIGN KEY %s REFERENCES %s %s ON UPDATE %s ON DELETE %s',
            $this->sql->escapeIdentifier($foreignKey->name),
            $this->getKeyColumnsDefintion(array_keys($foreignKey->columns)),
            $this->sql->escapeIdentifier($foreignKey->table),
            $this->getKeyColumnsDefintion($foreignKey->columns),
            $foreignKey->onUpdate,
            $foreignKey->onDelete,
        );
    }

    /** @param array<string> $columns */
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

                $offset = Type::int(array_search($after, array_keys($columns)));
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
            self::clearInstance($this->originalName, $this->db);
            self::addInstance([$this->db, $this->name], $this);
        }

        $this->originalName = $this->name;

        $columns = $this->columns;
        $this->columns = [];
        $this->columnsExisting = [];
        foreach ($columns as $column) {
            $this->columns[$column->name] = $column;
            $this->columnsExisting[$column->name] = $column->name;
        }

        $this->implicitOrder = [];
        $this->positions = [];

        $this->primaryKeyExisting = $this->primaryKey;

        $indexes = $this->indexes;
        $this->indexes = [];
        $this->indexesExisting = [];
        foreach ($indexes as $index) {
            $this->indexes[$index->name] = $index;
            $this->indexesExisting[$index->name] = $index->name;
        }

        $foreignKeys = $this->foreignKeys;
        $this->foreignKeys = [];
        $this->foreignKeysExisting = [];
        foreach ($foreignKeys as $foreignKey) {
            $this->foreignKeys[$foreignKey->name] = $foreignKey;
            $this->foreignKeysExisting[$foreignKey->name] = $foreignKey->name;
        }

        $this->modifiedColumns = [];
        $this->modifiedIndexes = [];
        $this->modifiedForeignKeys = [];
    }
}
