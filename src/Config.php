<?php

namespace Redaxo\Core;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

use function count;
use function dirname;
use function is_array;

/**
 * Class for handling configurations.
 * The configuration is persisted between requests.
 */
final class Config
{
    /** Flag to indicate if the config was initialized. */
    private static bool $initialized = false;

    /** path to the cache file. */
    private static string $cacheFile;

    /** Flag which indicates if database needs an update, because settings have changed. */
    private static bool $changed = false;

    /**
     * data read from database.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $data = [];

    /**
     * data which is modified during this request.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $changedData = [];

    /**
     * data which was deleted during this request.
     *
     * @var array<string, array<string, true>>
     */
    private static array $deletedData = [];

    private function __construct() {}

    /**
     * Method which saves an arbitary value associated to the given namespace and key.
     * If the second parameter is an associative array, all key/value pairs will be saved.
     *
     * The set-method returns TRUE when an existing value was overridden, otherwise FALSE is returned.
     *
     * @param string $namespace The namespace e.g. an addon name
     * @param string|array<string, mixed> $key The associated key or an associative array of key/value pairs
     * @param mixed $value The value to save
     *
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    public static function set(string $namespace, string|array $key, mixed $value = null): bool
    {
        self::init();

        if (is_array($key)) {
            $existed = false;
            foreach ($key as $k => $v) {
                $existed = self::set($namespace, $k, $v) || $existed;
            }
            return $existed;
        }

        if (!isset(self::$data[$namespace])) {
            self::$data[$namespace] = [];
        }

        $existed = isset(self::$data[$namespace][$key]);
        if (!$existed || self::$data[$namespace][$key] !== $value) {
            // keep track of changed data
            self::$changedData[$namespace][$key] = $value;

            // since it was re-added, do not longer mark as deleted
            unset(self::$deletedData[$namespace][$key]);

            // re-set the data in the container
            self::$data[$namespace][$key] = $value;
            self::$changed = true;
        }

        return $existed;
    }

    /**
     * Method which returns an associated value for the given namespace and key.
     * If $key is null, an array of all key/value pairs for the given namespace will be returned.
     *
     * If no value can be found for the given key/namespace combination $default is returned.
     *
     * @template T as ?string
     * @param string $namespace The namespace e.g. an addon name
     * @param T $key The associated key
     * @param mixed $default Default return value if no associated-value can be found
     * @return (T is string ? mixed|null : array<string, mixed>) the value for $key or $default if $key cannot be found in the given $namespace
     */
    public static function get(string $namespace, ?string $key = null, mixed $default = null): mixed
    {
        self::init();

        if (null === $key) {
            return self::$data[$namespace] ?? [];
        }

        return self::$data[$namespace][$key] ?? $default;
    }

    /**
     * Returns if the given key is set.
     *
     * @param string $namespace The namespace e.g. an addon name
     * @param string|null $key The associated key
     *
     * @return bool TRUE if the key is set, otherwise FALSE
     */
    public static function has(string $namespace, ?string $key = null): bool
    {
        self::init();

        if (null === $key) {
            return isset(self::$data[$namespace]);
        }

        return isset(self::$data[$namespace][$key]);
    }

    /**
     * Removes the setting associated with the given namespace and key.
     *
     * @param string $namespace The namespace e.g. an addon name
     * @param string $key The associated key
     *
     * @return bool TRUE if the value was found and removed, otherwise FALSE
     */
    public static function remove(string $namespace, string $key): bool
    {
        self::init();

        if (isset(self::$data[$namespace][$key])) {
            // keep track of deleted data
            self::$deletedData[$namespace][$key] = true;

            // since it will be deleted, do not longer mark as changed
            unset(self::$changedData[$namespace][$key]);
            if (empty(self::$changedData[$namespace])) {
                unset(self::$changedData[$namespace]);
            }

            // delete the data from the container
            unset(self::$data[$namespace][$key]);
            if (empty(self::$data[$namespace])) {
                unset(self::$data[$namespace]);
            }
            self::$changed = true;
            return true;
        }
        return false;
    }

    /**
     * Removes all settings associated with the given namespace.
     *
     * @param string $namespace The namespace e.g. an addon name
     *
     * @return bool TRUE if the namespace was found and removed, otherwise FALSE
     */
    public static function removeNamespace(string $namespace): bool
    {
        self::init();

        if (isset(self::$data[$namespace])) {
            foreach (self::$data[$namespace] as $key => $value) {
                self::remove($namespace, $key);
            }

            unset(self::$data[$namespace]);
            self::$changed = true;
            return true;
        }
        return false;
    }

    /** Refreshes Configuration by reloading config from db. */
    public static function refresh(): void
    {
        if (!self::$initialized) {
            self::init();

            return;
        }

        self::loadFromDb();

        self::generateCache();

        self::$changed = false;
        self::$changedData = [];
        self::$deletedData = [];
    }

    /** initilizes the Configuration class. */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$cacheFile = Path::coreCache('config.cache');

        // take care, so we are able to write a cache file on shutdown
        // (check here, since exceptions in shutdown functions are not visible to the user)
        $dir = dirname(self::$cacheFile);
        Dir::create($dir);
        if (!is_writable($dir)) {
            throw new RuntimeException('Configuration: cache dir "' . dirname(self::$cacheFile) . '" is not writable.');
        }

        // save cache on shutdown
        register_shutdown_function([self::class, 'save']);

        self::load();
        self::$initialized = true;
    }

    /** load the config-data. */
    private static function load(): void
    {
        // check if we can load the config from the filesystem
        if (!self::loadFromFile()) {
            // if not possible, fallback to load config from the db
            self::loadFromDb();
            // afterwards persist loaded data into file-cache
            self::generateCache();
        }
    }

    /**
     * load the config-data from a file-cache.
     *
     * @return bool Returns TRUE, if the data was successfully loaded from the file-cache, otherwise FALSE
     */
    private static function loadFromFile(): bool
    {
        // delete cache-file, will be regenerated on next request
        if (is_file(self::$cacheFile)) {
            self::$data = File::getCache(self::$cacheFile);
            return true;
        }
        return false;
    }

    /** load the config-data from database. */
    private static function loadFromDb(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'config');

        self::$data = [];
        foreach ($sql as $cfg) {
            self::$data[$cfg->getValue('namespace')][$cfg->getValue('key')] = json_decode($cfg->getValue('value'), true);
        }
    }

    /** save config to file-cache. */
    private static function generateCache(): void
    {
        if (File::putCache(self::$cacheFile, self::$data) <= 0) {
            throw new RuntimeException('Configuration: unable to write cache file "' . self::$cacheFile . '".');
        }
    }

    /** persists the config-data and truncates the file-cache. */
    public static function save(): void
    {
        // save cache only if changes happened
        if (!self::$changed) {
            return;
        }

        // after all no data needs to be deleted or update, so skip save
        if (empty(self::$deletedData) && empty(self::$changedData)) {
            return;
        }

        // delete cache-file; will be regenerated on next request
        File::delete(self::$cacheFile);

        // save all data to the db
        self::saveToDb();
        self::$changed = false;
        self::$changedData = [];
        self::$deletedData = [];
    }

    /** save the config-data into the db. */
    private static function saveToDb(): void
    {
        $sql = Sql::factory();
        // $sql->setDebug();

        // remove all deleted data
        if (self::$deletedData) {
            $sql->setTable(Core::getTable('config'));

            $where = [];
            $params = [];
            foreach (self::$deletedData as $namespace => $nsData) {
                if (0 === count($nsData)) {
                    continue;
                }
                $params[] = $namespace;
                $where[] = 'namespace = ? AND `key` IN (' . $sql->in(array_keys($nsData)) . ')';
            }
            if (count($where) > 0) {
                $sql->setWhere(implode("\n    OR ", $where), $params);
                $sql->delete();
            }
        }

        // update all changed data
        if (self::$changedData) {
            $sql->setTable(Core::getTable('config'));

            foreach (self::$changedData as $namespace => $nsData) {
                foreach ($nsData as $key => $value) {
                    $sql->addRecord(static function (Sql $record) use ($namespace, $key, $value) {
                        $record->setValue('namespace', $namespace);
                        $record->setValue('key', $key);
                        $record->setValue('value', json_encode($value));
                    });
                }
            }

            $sql->insertOrUpdate();
        }
    }
}
