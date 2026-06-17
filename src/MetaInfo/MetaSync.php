<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\Database\Table;

use function array_map;
use function str_starts_with;

/**
 * Synchronises the database columns backing the meta fields with the current {@see MetaSchema} definitions.
 *
 * Adding and modifying columns happens automatically. Dropping an obsolete column (one whose entity prefix is
 * managed but for which no field exists anymore) destroys data, so the decision is delegated to the caller via
 * the `$confirmDrop` callback; callers should keep the column (and warn) when running non-interactively.
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

        // Article and Category share the same table, so group entities by table to sync each table only once.
        $entitiesByTable = [];
        foreach (MetaEntity::cases() as $entity) {
            $entitiesByTable[$entity->table()][] = $entity;
        }

        foreach ($entitiesByTable as $tableName => $entities) {
            if ('' === $tableName) {
                continue;
            }

            $table = Table::get($tableName);

            $desired = [];
            foreach ($entities as $entity) {
                foreach (MetaSchema::getFields($entity) as $field) {
                    $column = $field->column($entity);
                    if (null === $column) {
                        continue;
                    }

                    $name = $column->getName();
                    $existing = $table->getColumn($name);
                    if (null === $existing) {
                        $added[] = $tableName . '.' . $name;
                    } elseif (!$existing->equals($column)) {
                        $modified[] = $tableName . '.' . $name;
                    }

                    $table->ensureColumn($column);
                    $desired[$name] = true;
                }
            }

            // Columns carrying a managed prefix but no longer defined by any field are obsolete.
            $prefixes = array_map(static fn (MetaEntity $entity): string => $entity->prefix(), $entities);
            foreach ($table->getColumns() as $name => $column) {
                if (isset($desired[$name]) || !self::hasManagedPrefix($name, $prefixes)) {
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

    /** @param list<string> $prefixes */
    private static function hasManagedPrefix(string $column, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($column, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
