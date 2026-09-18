<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\Database\Table;
use Redaxo\Core\MetaInfo\Field\MetaField;

use function array_filter;
use function str_starts_with;

/**
 * Synchronises the database columns backing the meta fields with the current {@see MetaSchema} definitions.
 *
 * Adding and modifying columns happens automatically. Dropping an obsolete column (one carrying the
 * {@see MetaField::COLUMN_PREFIX} but for which no field exists anymore) destroys data, so the decision is delegated
 * to the caller via the `$confirmDrop` callback; callers should keep the column (and warn) when running
 * non-interactively.
 *
 * Run from the `migrate` command after the core/addon base schema is in place.
 *
 * @internal
 */
final class MetaSync
{
    /**
     * @param callable(string $table, string $column): bool $confirmDrop returns `true` to drop an obsolete column
     *
     * @return array{added: list<string>, modified: list<string>, dropped: list<string>, kept: list<string>} affected columns as `table.column`
     */
    public static function run(callable $confirmDrop): array
    {
        $added = [];
        $modified = [];
        $dropped = [];
        $kept = [];

        // Translatable fields go to the translation table of their entity, so group the fields by their target table.
        /** @var array<non-empty-string, list<MetaField>> $fieldsByTable */
        $fieldsByTable = [];
        foreach (MetaEntity::cases() as $entity) {
            foreach (array_filter([$entity->table(), $entity->translationTable()]) as $tableName) {
                $fieldsByTable[$tableName] ??= [];
            }
            foreach (MetaSchema::getFields($entity) as $field) {
                $fieldsByTable[$entity->tableForField($field)][] = $field;
            }
        }

        /** @var non-empty-string $tableName */
        foreach ($fieldsByTable as $tableName => $fields) {
            $table = Table::get($tableName);

            $desired = [];
            foreach ($fields as $field) {
                $column = $field->column();
                if (null === $column) {
                    continue;
                }

                $name = $column->name;
                $existing = $table->getColumn($name);
                if (null === $existing) {
                    $added[] = $tableName . '.' . $name;
                } elseif (!$existing->equals($column)) {
                    $modified[] = $tableName . '.' . $name;
                }

                $table->ensureColumn($column);
                $desired[$name] = true;
            }

            // Meta columns no longer defined by any field are obsolete.
            foreach ($table->getColumns() as $name => $column) {
                if (isset($desired[$name]) || !str_starts_with($name, MetaField::COLUMN_PREFIX)) {
                    continue;
                }

                if ($confirmDrop($tableName, $name)) {
                    $table->removeColumn($name);
                    $dropped[] = $tableName . '.' . $name;
                } else {
                    $kept[] = $tableName . '.' . $name;
                }
            }

            $table->alter();
        }

        return ['added' => $added, 'modified' => $modified, 'dropped' => $dropped, 'kept' => $kept];
    }
}
