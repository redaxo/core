<?php

namespace Redaxo\Core\Migration;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

/**
 * A single, one-time database migration.
 *
 * Migrations complement the `install()` hooks: those describe the target state of a package's tables and run on
 * every `migrate`, a migration describes a transition that happens exactly once — typically a data backfill, or
 * DDL for tables no package owns. Structural changes a package owns are usually better placed in its `install()`,
 * where the order within the hook is up to you.
 *
 * The file returns the migration instance, so it does not have to be autoloadable:
 *
 * ```php
 * // migrations/2026-08-21-143000-add_sku_to_products.php
 * return new class extends Migration {
 *     public function up(): void
 *     {
 *         Table::get('rex_shop_product')->ensureColumn(Column::varchar('sku', 64, nullable: true))->alter();
 *     }
 * };
 * ```
 *
 * Migrations run without booted addons, so that they also work while the application is broken because the
 * migration is still missing: use {@see Sql}, {@see Table} and other core primitives, not addon runtime APIs.
 *
 * Keep them small — DDL is not transactional, and only whole migrations are recorded as executed, so a migration
 * that dies halfway runs again from the start on the next attempt.
 */
abstract class Migration
{
    abstract public function up(): void;
}
