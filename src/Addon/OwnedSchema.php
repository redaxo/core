<?php

namespace Redaxo\Core\Addon;

/**
 * The tables and columns an addon owns, declared via {@see Addon::getOwnedSchema()}.
 *
 * Removed once the addon is no longer installed via composer — on every instance, by `migrate:cleanup`.
 */
final readonly class OwnedSchema
{
    /**
     * @param list<non-empty-string> $tables Tables that belong to the addon entirely
     * @param array<non-empty-string, list<non-empty-string>> $columns Columns the addon added to foreign tables, keyed by table
     */
    public function __construct(
        public array $tables = [],
        public array $columns = [],
    ) {}
}
