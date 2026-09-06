<?php

namespace Redaxo\Core\Migration;

use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Path;

use function count;
use function glob;
use function in_array;
use function is_dir;
use function sprintf;
use function strlen;
use function substr;
use function usort;

use const DIRECTORY_SEPARATOR;

/**
 * Finds, orders and executes {@see Migration} files and keeps track of which of them already ran.
 *
 * Migrations are plain files in a `migrations/` directory of the core, of an installed addon or of the project.
 * They are deliberately found by `glob` and not via {@see ClassDiscovery}: migrations repair a system whose
 * schema has fallen behind, so finding them must not depend on autoload state, addon activation or a cache.
 *
 * Installed but deactivated addons are included, so their schema does not fall behind.
 */
final class Migrator
{
    /** Package name used for migrations shipped by the core. */
    public const string CORE = 'core';

    /** Package name used for migrations of the project itself. */
    public const string PROJECT = 'project';

    private const string DIRECTORY = 'migrations';

    /**
     * Returns all migrations that exist on disk, ordered by id.
     *
     * @param non-empty-string|null $package Limit the result to a single package
     * @return list<MigrationFile>
     */
    public static function getMigrations(?string $package = null): array
    {
        $migrations = [];

        foreach (self::getDirectories() as $packageName => $directory) {
            if (null !== $package && $package !== $packageName) {
                continue;
            }

            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $path) {
                $id = substr(Path::basename($path), 0, -strlen('.php'));

                if ('' === $id) {
                    continue;
                }

                $migrations[] = new MigrationFile($packageName, $id, $path);
            }
        }

        // Sorted by id across all packages, with the package as tie breaker for a stable order.
        usort($migrations, static fn (MigrationFile $a, MigrationFile $b): int => [$a->id, $a->package] <=> [$b->id, $b->package]);

        return $migrations;
    }

    /**
     * Returns the migrations that have not been executed yet, ordered by id.
     *
     * @param non-empty-string|null $package Limit the result to a single package
     * @return list<MigrationFile>
     */
    public static function getPending(?string $package = null): array
    {
        $executed = self::getExecuted();

        $pending = [];
        foreach (self::getMigrations($package) as $migration) {
            if (!isset($executed[$migration->package][$migration->id])) {
                $pending[] = $migration;
            }
        }

        return $pending;
    }

    /**
     * Executes the migration and records it as executed.
     *
     * @internal
     */
    public static function run(MigrationFile $migration): void
    {
        self::load($migration->path)->up();

        self::markExecuted($migration);
    }

    /**
     * Records the migration as executed without running it.
     *
     * @internal
     */
    public static function markExecuted(MigrationFile $migration): void
    {
        Sql::factory()
            ->setTable(Core::getTable('migration'))
            ->setValue('package', $migration->package)
            ->setValue('id', $migration->id)
            ->setValue('executed', Sql::datetime())
            ->insertOrUpdate();
    }

    /**
     * Records all migrations of the given package (or of all packages) as executed without running them.
     *
     * Used for fresh installations: `install()` created the schema in the shape the current code describes, so
     * the migrations that once led there must not run again.
     *
     * @param non-empty-string|null $package
     * @return int Number of migrations that have been marked
     *
     * @internal
     */
    public static function baseline(?string $package = null): int
    {
        $pending = self::getPending($package);

        foreach ($pending as $migration) {
            self::markExecuted($migration);
        }

        return count($pending);
    }

    /**
     * Drops the ledger entries of the given package, e.g. because it was uninstalled and its schema is gone.
     *
     * @internal
     */
    public static function forget(string $package): void
    {
        if (!self::ledgerExists()) {
            return;
        }

        $sql = Sql::factory();
        $sql->setQuery('DELETE FROM ' . $sql->escapeIdentifier(Core::getTable('migration')) . ' WHERE `package` = ?', [$package]);
    }

    /**
     * Returns the migrations directory of the given package.
     *
     * @param non-empty-string $package
     * @return non-empty-string
     */
    public static function getDirectory(string $package): string
    {
        return match ($package) {
            self::CORE => Path::core(self::DIRECTORY),
            self::PROJECT => Path::base(self::DIRECTORY),
            default => Path::addon($package, self::DIRECTORY),
        };
    }

    /** @return array<non-empty-string, non-empty-string> Map of package name to its migrations directory */
    private static function getDirectories(): array
    {
        $packages = [self::CORE, ...AddonManager::getInstalledAddonOrder(), self::PROJECT];

        $directories = [];
        foreach ($packages as $package) {
            $directories[$package] = self::getDirectory($package);
        }

        return $directories;
    }

    /** The ledger table is created by the core schema, which may not have run yet. */
    private static function ledgerExists(): bool
    {
        return in_array(Core::getTable('migration'), Sql::factory()->getTables(Core::getTablePrefix()), true);
    }

    /** @return array<string, array<string, true>> Executed migration ids, grouped by package */
    private static function getExecuted(): array
    {
        if (!self::ledgerExists()) {
            return [];
        }

        $sql = Sql::factory();

        $executed = [];
        foreach ($sql->getArray('SELECT `package`, `id` FROM ' . $sql->escapeIdentifier(Core::getTable('migration'))) as $row) {
            $executed[(string) $row['package']][(string) $row['id']] = true;
        }

        return $executed;
    }

    /** Loads the migration file in a scope of its own. */
    private static function load(string $path): Migration
    {
        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Migration file "%s" must return an instance of %s.', $path, Migration::class));
        }

        return $migration;
    }
}
