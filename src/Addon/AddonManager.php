<?php

namespace Redaxo\Core\Addon;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function array_values;
use function class_exists;
use function dirname;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_subclass_of;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class AddonManager
{
    use FactoryTrait;

    /**
     * Config key for the addons whose `install()` has already run on this instance, with the schema they own.
     *
     * This is instance state, written by `migrate` only — never a switch. It tells an addon that is new to
     * this instance (whose schema `install()` just created in the shape the current code describes) apart
     * from one that has been here before (whose pending migrations still have to run), and it keeps what an
     * addon owns available after its code is gone.
     */
    private const string CONFIG_ADDONS_INSTALLED = 'addons_installed';

    protected string $message = '';

    final protected function __construct(
        protected readonly Addon $addon,
    ) {}

    /** Creates the manager for the addon. */
    public static function factory(Addon $addon): static
    {
        $class = static::getFactoryClass();
        return new $class($addon);
    }

    /** Returns the message. */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Brings the addon's schema and data in line with its code by running its `install()` hook.
     *
     * Idempotent by contract, because `migrate` runs it on every deploy.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function install(): bool
    {
        try {
            I18n::addDirectory($this->addon->getPath('lang'));

            $firstInstall = !self::hasBeenInstalled($this->addon->name);

            // rebuilt from scratch so that files removed from the addon don't linger; install() may add generated ones
            if (!Dir::delete($this->addon->getAssetsPath())) {
                throw new UserMessageException($this->i18n('install_cant_delete_files'));
            }
            $assets = $this->addon->getPath('assets');
            if (is_dir($assets) && !Dir::copy($assets, $this->addon->getAssetsPath())) {
                throw new UserMessageException($this->i18n('install_cant_copy_files'));
            }

            // run install hook (can only abort by throwing a UserMessageException)
            $this->addon->install();

            foreach ($this->addon->defaultConfig as $key => $value) {
                if (!$this->addon->hasConfig($key)) {
                    $this->addon->setConfig($key, $value);
                }
            }

            if ($firstInstall) {
                // install() created the current schema, so the migrations leading there must not run again
                Migrator::baseline($this->addon->name);
            }

            self::recordInstalled($this->addon);

            $this->message = $this->i18n('installed', $this->addon->name);

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->message = $this->i18n('no_install', $this->addon->name) . '<br />' . $this->message;

        return false;
    }

    /** Translates the given key, prefixed with `addon_`. */
    protected function i18n(string $key, string|int ...$replacements): string
    {
        return I18n::msg('addon_' . $key, ...$replacements);
    }

    /**
     * Returns the addons whose `install()` has already run on this instance, with their composer package and the
     * schema they declared on the last run.
     *
     * @return array<non-empty-string, array{package?: string, tables?: list<non-empty-string>, columns?: array<non-empty-string, list<non-empty-string>>}>
     */
    private static function getInstalledAddons(): array
    {
        $addons = Type::array(Core::getConfig(self::CONFIG_ADDONS_INSTALLED, []));

        // instances from before the owned schema was recorded store a plain list of names
        if (array_is_list($addons)) {
            $addons = array_fill_keys(array_map(Type::string(...), $addons), []);
        }

        /** @var array<non-empty-string, array{package?: string, tables?: list<non-empty-string>, columns?: array<non-empty-string, list<non-empty-string>>}> */
        return $addons;
    }

    /** Returns whether the addon's `install()` has already run on this instance. */
    private static function hasBeenInstalled(string $addon): bool
    {
        return isset(self::getInstalledAddons()[$addon]);
    }

    private static function recordInstalled(Addon $addon): void
    {
        $addons = self::getInstalledAddons();
        $schema = $addon->getOwnedSchema();

        $entry = ['package' => $addon->package, 'tables' => $schema->tables, 'columns' => $schema->columns];

        if (($addons[$addon->name] ?? null) === $entry) {
            return;
        }

        $addons[$addon->name] = $entry;
        ksort($addons);

        Core::setConfig(self::CONFIG_ADDONS_INSTALLED, $addons);
    }

    /**
     * Returns the addons that have data on this instance but are no longer installed via composer.
     *
     * {@see removeOrphan()} cleans them up, unless {@see getOrphanRemovalBlocker()} objects.
     *
     * @return list<non-empty-string>
     *
     * @internal
     */
    public static function getOrphans(): array
    {
        return array_values(array_filter(
            array_keys(self::getInstalledAddons()),
            static fn (string $addon): bool => !Addon::exists($addon),
        ));
    }

    /**
     * Returns why the orphaned addon must not be removed, or null if it can be.
     *
     * An addon only counts as removed if the project's `composer.lock` does not list it any more: after a
     * `composer install --no-dev` a dev-only addon looks exactly like an orphan, and dropping its tables because
     * of that would be fatal.
     *
     * @internal
     */
    public static function getOrphanRemovalBlocker(string $addon): ?string
    {
        $package = self::getInstalledAddons()[$addon]['package'] ?? null;

        if (null === $package) {
            return 'its composer package is unknown';
        }

        $locked = self::getLockedPackages();

        if (null === $locked) {
            return 'the project\'s composer.lock is missing';
        }

        if (isset($locked[$package])) {
            return sprintf('"%s" is still in the composer.lock (a dev package after `composer install --no-dev`?)', $package);
        }

        return null;
    }

    /**
     * Removes the declared schema and all data of an addon that is no longer installed via composer.
     *
     * @param non-empty-string $addon
     * @return array{tables: int, columns: int} Number of removed tables and columns
     *
     * @internal
     */
    public static function removeOrphan(string $addon): array
    {
        $entry = self::getInstalledAddons()[$addon] ?? [];
        $tables = $entry['tables'] ?? [];
        $columns = $entry['columns'] ?? [];

        $sql = Sql::factory();
        $sql->setQuery('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($columns as $table => $names) {
                $table = Table::get($table);

                if (!$table->exists()) {
                    continue;
                }

                foreach ($names as $name) {
                    $table->removeColumn($name);
                }
                $table->alter();
            }

            foreach ($tables as $table) {
                Table::get($table)->drop();
            }
        } finally {
            $sql->setQuery('SET FOREIGN_KEY_CHECKS = 1');
        }

        Config::removeNamespace($addon);
        Dir::delete(Path::addonData($addon));
        Dir::delete(Path::addonCache($addon));
        Dir::delete(Path::addonAssets($addon));
        Migrator::forget($addon);

        $addons = self::getInstalledAddons();
        unset($addons[$addon]);
        Core::setConfig(self::CONFIG_ADDONS_INSTALLED, $addons);

        return ['tables' => \count($tables), 'columns' => array_sum(array_map(count(...), $columns))];
    }

    /**
     * Returns the names of all packages in the project's `composer.lock`, or null if there is none.
     *
     * @return array<string, true>|null
     */
    private static function getLockedPackages(): ?array
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            // the loader of the project, not one of a dependency with a scoped vendor
            if (false === $loader->findFile(Addon::class)) {
                continue;
            }

            $json = File::get(dirname($vendorDir) . '/composer.lock');

            if (null === $json) {
                return null;
            }

            /** @var array{packages?: list<array{name: string}>, packages-dev?: list<array{name: string}>} $lock */
            $lock = Type::array(json_decode($json, true, flags: JSON_THROW_ON_ERROR));

            $names = array_column([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []], 'name');

            return array_fill_keys($names, true);
        }

        return null;
    }

    /**
     * Returns the addon-class mapping for all registered `redaxo-addon` packages, read from `vendor/composer/installed.json`.
     *
     * @internal
     *
     * @return array<non-empty-string, class-string<Addon>>
     */
    public static function getAddonClasses(): array
    {
        $classes = [];

        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $_loader) {
            $jsonPath = $vendorDir . '/composer/installed.json';
            if (!is_file($jsonPath)) {
                continue;
            }

            $json = File::get($jsonPath);
            if (!$json) {
                continue;
            }

            /** @var array{packages?: list<array<string, mixed>>} $data */
            $data = Type::array(json_decode($json, true, flags: JSON_THROW_ON_ERROR));

            foreach ($data['packages'] ?? [] as $package) {
                if (($package['type'] ?? null) !== 'redaxo-addon') {
                    continue;
                }

                $name = Path::basename(Type::string($package['name'] ?? ''));
                /** @var array<string, mixed> $extra */
                $extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
                /** @var array<string, mixed> $redaxoExtra */
                $redaxoExtra = is_array($extra['redaxo'] ?? null) ? $extra['redaxo'] : [];
                $class = $redaxoExtra['addon-class'] ?? null;

                if (!is_string($class) || '' === $class || '' === $name) {
                    continue;
                }
                if (!class_exists($class)) {
                    throw new RuntimeException(sprintf('Addon class "%s" of addon "%s" does not exist.', $class, $name));
                }
                if (!is_subclass_of($class, Addon::class)) {
                    throw new RuntimeException(sprintf('Addon class "%s" of addon "%s" must extend %s.', $class, $name, Addon::class));
                }

                /** @var class-string<Addon> $class */
                $classes[$name] = $class;
            }
        }

        return $classes;
    }

    /** @return array<non-empty-string, non-empty-string> */
    public static function getComposerPackages(): array
    {
        $packages = [];
        foreach (InstalledVersions::getInstalledPackagesByType('redaxo-addon') as $package) {
            $addon = Path::basename($package);

            if (isset($packages[$addon])) {
                throw new RuntimeException(sprintf('The composer packages "%s" and "%s" have the same addon name "%s".', $packages[$addon], $package, $addon));
            }

            $packages[$addon] = $package;
        }

        /** @var array<non-empty-string, non-empty-string> */
        return $packages;
    }
}
