<?php

namespace Redaxo\Core\Database;

use Redaxo\Core\Core;

use function count;
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
        $idColumn = new Column('id', 'int(10) unsigned', false, null, 'auto_increment');

        foreach ($table->getColumns() as $column) {
            if ($primaryKeyIsId && $column->equals($idColumn)) {
                $code .= "\n    ->ensurePrimaryIdColumn()";
                $setPrimaryKey = false;

                continue;
            }

            $code .= "\n    ->ensureColumn(" . $this->getColumn($column) . ')';
        }

        $code = str_replace(
            '
    ->ensureColumn(new \\' . Column::class . "('createdate', 'datetime'))
    ->ensureColumn(new \\" . Column::class . "('createuser', 'varchar(255)'))
    ->ensureColumn(new \\" . Column::class . "('updatedate', 'datetime'))
    ->ensureColumn(new \\" . Column::class . "('updateuser', 'varchar(255)'))",
            '
    ->ensureGlobalColumns()',
            $code,
        );

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

    private function getColumn(Column $column): string
    {
        $parameters = [];
        $nonDefault = false;

        if (null !== $column->getComment()) {
            $parameters[] = $this->scalar($column->getComment());
            $nonDefault = true;
        }

        if ($nonDefault || null !== $column->getExtra()) {
            $parameters[] = $this->scalar($column->getExtra());
            $nonDefault = true;
        }

        if ($nonDefault || null !== $column->getDefault()) {
            $parameters[] = $this->scalar($column->getDefault());
            $nonDefault = true;
        }

        if ($nonDefault || $column->isNullable()) {
            $parameters[] = $this->scalar($column->isNullable());
        }

        $parameters[] = $this->scalar($column->getType());
        $parameters[] = $this->scalar($column->getName());

        return 'new \\' . Column::class . '(' . implode(', ', array_reverse($parameters)) . ')';
    }

    private function getIndex(Index $index): string
    {
        $parameters = [
            $this->scalar($index->getName()),
            $this->simpleArray($index->getColumns()),
        ];

        if (Index::INDEX !== $type = $index->getType()) {
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
            $this->scalar($foreignKey->getName()),
            $this->tableName($foreignKey->getTable()),
            $this->map($foreignKey->getColumns()),
        ];

        $options = [
            ForeignKey::RESTRICT => '\\' . ForeignKey::class . '::RESTRICT',
            ForeignKey::NO_ACTION => '\\' . ForeignKey::class . '::NO_ACTION',
            ForeignKey::CASCADE => '\\' . ForeignKey::class . '::CASCADE',
            ForeignKey::SET_NULL => '\\' . ForeignKey::class . '::SET_NULL',
        ];

        $nonDefaultOnDelete = ForeignKey::RESTRICT !== $foreignKey->getOnDelete();

        if ($nonDefaultOnDelete || ForeignKey::RESTRICT !== $foreignKey->getOnUpdate()) {
            $parameters[] = $options[$foreignKey->getOnUpdate()];
        }

        if ($nonDefaultOnDelete) {
            $parameters[] = $options[$foreignKey->getOnDelete()];
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
