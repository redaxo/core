<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Core;

use function count;
use function in_array;
use function is_string;
use function strlen;

/**
 * Class for generating the php code for a Table definition.
 *
 * Especially useful to generate the code for the `install.php` of packages.
 */
final readonly class SchemaDumper
{
    /** Dumps the schema for the given table as php code (using `Table`). */
    public function dumpTable(Table $table): string
    {
        $code = '\\' . Table::class . '::get(' . $this->tableName($table->getName()) . ')';

        $setPrimaryKey = true;
        $primaryKeyIsId = ['id'] === $table->getPrimaryKey();
        $idColumn = Column::int('id', unsigned: true, autoIncrement: true);

        foreach ($table->getColumns() as $column) {
            if ($primaryKeyIsId && $column->equals($idColumn)) {
                $code .= "\n    ->ensurePrimaryIdColumn()";
                $setPrimaryKey = false;

                continue;
            }

            $code .= "\n    ->ensureColumn(" . $this->getColumn($column) . ')';
        }

        $globalColumns = '';
        foreach ($this->getGlobalColumns() as $globalColumn) {
            $globalColumns .= "\n    ->ensureColumn(" . $this->getColumn($globalColumn) . ')';
        }

        $code = str_replace($globalColumns, "\n    ->ensureGlobalColumns()", $code);

        if ($setPrimaryKey && $primaryKey = $table->getPrimaryKey()) {
            $code .= "\n    ->setPrimaryKey(" . $this->getPrimaryKey($primaryKey) . ')';
        }

        foreach ($table->getIndexes() as $index) {
            $code .= "\n    ->ensureIndex(" . $this->getIndex($index) . ')';
        }

        foreach ($table->getForeignKeys() as $foreignKey) {
            $code .= "\n    ->ensureForeignKey(" . $this->getForeignKey($foreignKey) . ')';
        }

        $code .= "\n    ->ensure();\n";

        return $code;
    }

    /**
     * The columns added by `Table::ensureGlobalColumns()`, to detect them in the generated code.
     *
     * @return list<Column>
     */
    private function getGlobalColumns(): array
    {
        return [
            Column::datetime('createdate'),
            Column::varchar('createuser', 255),
            Column::datetime('updatedate'),
            Column::varchar('updateuser', 255),
        ];
    }

    private function getColumn(Column $column): string
    {
        return $this->getColumnByFactory($column) ?? $this->getColumnByConstructor($column);
    }

    /**
     * Dumps a column as a call to one of the factory methods of `Column`.
     *
     * Returns null for columns that no factory method can express, e.g. exotic types, non-default
     * integer display widths or an unknown extra clause.
     */
    private function getColumnByFactory(Column $column): ?string
    {
        $type = $column->type;
        $extra = $column->extra;
        $default = $column->default;

        // Arguments before and after the common `$nullable` and `$default` parameters.
        $leading = [];
        $trailing = [];

        if ('tinyint(1)' === $type) {
            // Assume a boolean: any other use of `tinyint(1)` results in the same column anyway.
            if (null !== $extra || !in_array($default, [null, '0', '1'], true)) {
                return null;
            }

            $method = 'bool';
            $default = null === $default ? null : ('1' === $default ? 'true' : 'false');
        } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)( unsigned)?$/', $type, $match)) {
            $unsigned = isset($match[2]);

            if (null !== $default && !preg_match('/^-?\d+$/', $default)) {
                return null;
            }

            if (null !== $extra) {
                if (0 !== strcasecmp('auto_increment', $extra)) {
                    return null;
                }

                $trailing['autoIncrement'] = 'true';
            }

            $method = $match[1];

            if ($unsigned) {
                $leading['unsigned'] = 'true';
            }
        } elseif (preg_match('/^varchar\((\d+)\)$/', $type, $match)) {
            if (null !== $extra) {
                return null;
            }

            $method = 'varchar';
            $leading[] = $match[1];
            $default = null === $default ? null : $this->scalar($default);
        } elseif (preg_match('/^decimal\((\d+),(\d+)\)( unsigned)?$/', $type, $match)) {
            if (null !== $extra) {
                return null;
            }

            $method = 'decimal';
            $leading[] = $match[1];
            $leading[] = $match[2];

            if (isset($match[3])) {
                $leading['unsigned'] = 'true';
            }

            $default = null === $default ? null : $this->scalar($default);
        } elseif (preg_match('/^(datetime|time)(?:\((\d+)\))?$/', $type, $match)) {
            // An expression default or an `on update` clause has no factory method on purpose.
            if (null !== $extra || $column->hasCurrentTimestampDefault()) {
                return null;
            }

            $method = $match[1];

            if (isset($match[2])) {
                $leading['precision'] = $match[2];
            }

            $default = null === $default ? null : $this->scalar($default);
        } elseif (in_array($type, ['text', 'mediumtext', 'longtext', 'date'], true)) {
            if (null !== $extra) {
                return null;
            }

            $method = $type;
            $default = null === $default ? null : $this->scalar($default);
        } else {
            return null;
        }

        $arguments = [$this->scalar($column->name)];

        foreach ($leading as $name => $value) {
            $arguments[] = is_string($name) ? $name . ': ' . $value : $value;
        }

        if ($column->nullable) {
            $arguments[] = 'nullable: true';
        }

        if (null !== $default) {
            $arguments[] = 'default: ' . $default;
        }

        foreach ($trailing as $name => $value) {
            $arguments[] = $name . ': ' . $value;
        }

        if (null !== $column->comment) {
            $arguments[] = 'comment: ' . $this->scalar($column->comment);
        }

        return '\\' . Column::class . '::' . $method . '(' . implode(', ', $arguments) . ')';
    }

    private function getColumnByConstructor(Column $column): string
    {
        $arguments = [$this->scalar($column->name), $this->scalar($column->type)];

        if ($column->nullable) {
            $arguments[] = 'nullable: true';
        }

        if (null !== $column->default) {
            $arguments[] = 'default: ' . $this->scalar($column->default);
        }

        if (null !== $column->extra) {
            $arguments[] = 'extra: ' . $this->scalar($column->extra);
        }

        if (null !== $column->comment) {
            $arguments[] = 'comment: ' . $this->scalar($column->comment);
        }

        return 'new \\' . Column::class . '(' . implode(', ', $arguments) . ')';
    }

    private function getIndex(Index $index): string
    {
        $parameters = [
            $this->scalar($index->name),
            $this->simpleArray($index->columns),
        ];

        if (Index::INDEX !== $type = $index->type) {
            $parameters[] = match ($type) {
                Index::UNIQUE => '\\' . Index::class . '::UNIQUE',
                Index::FULLTEXT => '\\' . Index::class . '::FULLTEXT',
            };
        }

        return 'new \\' . Index::class . '(' . implode(', ', $parameters) . ')';
    }

    private function getForeignKey(ForeignKey $foreignKey): string
    {
        $parameters = [
            $this->scalar($foreignKey->name),
            $this->tableName($foreignKey->table),
            $this->map($foreignKey->columns),
        ];

        $options = [
            ForeignKey::RESTRICT => '\\' . ForeignKey::class . '::RESTRICT',
            ForeignKey::NO_ACTION => '\\' . ForeignKey::class . '::NO_ACTION',
            ForeignKey::CASCADE => '\\' . ForeignKey::class . '::CASCADE',
            ForeignKey::SET_NULL => '\\' . ForeignKey::class . '::SET_NULL',
        ];

        $nonDefaultOnDelete = ForeignKey::RESTRICT !== $foreignKey->onDelete;

        if ($nonDefaultOnDelete || ForeignKey::RESTRICT !== $foreignKey->onUpdate) {
            $parameters[] = $options[$foreignKey->onUpdate];
        }

        if ($nonDefaultOnDelete) {
            $parameters[] = $options[$foreignKey->onDelete];
        }

        return 'new \\' . ForeignKey::class . '(' . implode(', ', $parameters) . ')';
    }

    /** @param list<string> $primaryKey */
    private function getPrimaryKey(array $primaryKey): string
    {
        if (1 === count($primaryKey)) {
            return $this->scalar(reset($primaryKey));
        }

        return $this->simpleArray($primaryKey);
    }

    private function tableName(string $name): string
    {
        if (!str_starts_with($name, Core::getTablePrefix())) {
            return $this->scalar($name);
        }

        $name = substr($name, strlen(Core::getTablePrefix()));

        return '\\' . Core::class . '::getTable(' . $this->scalar($name) . ')';
    }

    private function scalar(string|bool|null $scalar): string
    {
        if (null === $scalar) {
            return 'null';
        }

        /** @psalm-taint-escape html */ // https://github.com/vimeo/psalm/issues/4669
        $scalar = $scalar;

        return var_export($scalar, true);
    }

    /** @param list<string|bool|null> $list */
    private function simpleArray(array $list): string
    {
        $parts = [];

        foreach ($list as $value) {
            $parts[] = $this->scalar($value);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /** @param array<string, string|bool|null> $map */
    private function map(array $map): string
    {
        $parts = [];

        foreach ($map as $key => $value) {
            $parts[] = $this->scalar($key) . ' => ' . $this->scalar($value);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
