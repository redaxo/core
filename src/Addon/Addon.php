<?php

namespace Redaxo\Core\Addon;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Redaxo\Core\Addon\ExtensionPoint\AddonCacheDeleted;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Exception\YamlParseException;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;

use function assert;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const EXTR_SKIP;
use const JSON_THROW_ON_ERROR;

abstract class Addon
{
    final public const string FILE_PACKAGE = 'package.yml';

    private const string PROPERTIES_CACHE_FILE = 'packages.cache';

    /**
     * Array of all addons.
     *
     * @var array<non-empty-string, self>
     */
    private static array $addons = [];

    /** @var non-empty-string */
    public private(set) string $path {
        get {
            if (isset($this->path)) {
                return $this->path;
            }

            try {
                return $this->path = realpath(InstalledVersions::getInstallPath($this->package));
            } catch (OutOfBoundsException) {
                return $this->path = realpath(InstalledVersions::getRootPackage()['install_path']) . '/vendor/' . $this->package;
            }
        }
    }

    /** Loading position relative to other addons during boot. Override to load this addon early or late. */
    public protected(set) LoadOrder $load = LoadOrder::Normal;

    /**
     * Properties.
     *
     * @var array<string, mixed>
     */
    private array $properties = [];

    /** Flag whether the properties of package.yml are loaded. */
    private bool $propertiesLoaded = false;

    /** @var array<string, mixed>|null */
    private ?array $composerJson = null;

    final private function __construct(
        /** @var non-empty-string Composer package name */
        final public readonly string $package,
        /** @var non-empty-string Name of the addon */
        final public readonly string $name,
    ) {}

    /**
     * Returns the addon by the given name, or `null` if it does not exist.
     *
     * @param string $addon Addon name
     */
    final public static function get(string $addon): ?self
    {
        return self::$addons[$addon] ?? null;
    }

    /**
     * Returns the addon by the given name.
     *
     * @psalm-assert =non-empty-string $addon
     */
    final public static function require(string $addon): self
    {
        if (!isset(self::$addons[$addon])) {
            throw new RuntimeException(sprintf('Required addon "%s" does not exist.', $addon));
        }

        return self::$addons[$addon];
    }

    /**
     * Returns if the addon exists.
     *
     * @param string $addon Addon name
     *
     * @psalm-assert-if-true =non-empty-string $addon
     */
    final public static function exists(string $addon): bool
    {
        return isset(self::$addons[$addon]);
    }

    /** @return non-empty-string */
    final public function getPath(string $file = ''): string
    {
        return Path::addon($this->name, $file);
    }

    /** @return non-empty-string */
    final public function getAssetsPath(string $file = ''): string
    {
        return Path::addonAssets($this->name, $file);
    }

    /** @return non-empty-string */
    final public function getAssetsUrl(string $file = ''): string
    {
        return Url::addonAssets($this->name, $file);
    }

    /** @return non-empty-string */
    final public function getDataPath(string $file = ''): string
    {
        return Path::addonData($this->name, $file);
    }

    /** @return non-empty-string */
    final public function getCachePath(string $file = ''): string
    {
        return Path::addonCache($this->name, $file);
    }

    /**
     * @see Config::set()
     * @param string|array<string, mixed> $key The associated key or an associative array of key/value pairs
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    final public function setConfig(string|array $key, mixed $value = null): bool
    {
        return Config::set($this->name, $key, $value);
    }

    /**
     * @see Config::get()
     *
     * @template T as ?string
     * @param T $key The associated key
     * @param mixed $default Default return value if no associated-value can be found
     * @return (T is string ? mixed|null : array<string, mixed>) the value for $key or $default if $key cannot be found in the given $namespace
     */
    final public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        /** @psalm-suppress MixedReturnStatement */
        return Config::get($this->name, $key, $default);
    }

    /** @see Config::has() */
    final public function hasConfig(?string $key = null): bool
    {
        return Config::has($this->name, $key);
    }

    /** @see Config::remove() */
    final public function removeConfig(string $key): bool
    {
        return Config::remove($this->name, $key);
    }

    /** @param non-empty-string $key */
    final public function setProperty(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    /** @param non-empty-string $key */
    final public function getProperty(string $key, mixed $default = null): mixed
    {
        if ($this->hasProperty($key)) {
            return $this->properties[$key];
        }
        return $default;
    }

    /** @param non-empty-string $key */
    final public function hasProperty(string $key): bool
    {
        if (!isset($this->properties[$key]) && !$this->propertiesLoaded) {
            $this->loadProperties();
        }
        return isset($this->properties[$key]);
    }

    /** @param non-empty-string $key */
    final public function removeProperty(string $key): void
    {
        unset($this->properties[$key]);
    }

    /** Returns if the addon is available (activated and installed). */
    final public function isAvailable(): bool
    {
        return $this->isInstalled() && (bool) $this->getProperty('status', false);
    }

    /** Returns if the addon is installed. */
    final public function isInstalled(): bool
    {
        return (bool) $this->getProperty('install', false);
    }

    final public function getAuthor(?string $default = null): ?string
    {
        $composerJson = $this->getComposerJson();

        /** @var array<string, array{name?: string}> $authors */
        $authors = $composerJson['authors'] ?? [];
        if (!$authors) {
            return $default;
        }

        $names = array_filter(array_column($authors, 'name'));

        return $names ? implode(', ', $names) : $default;
    }

    /** @param string|null $format See {@link Formatter::version()} */
    final public function getVersion(?string $format = null): string
    {
        $version = $this->getProperty('version');

        if (null === $version) {
            $version = InstalledVersions::getPrettyVersion($this->package) ?? '';
            $this->setProperty('version', $version);
        }

        Type::string($version);

        if ($format) {
            return Formatter::version($version, $format);
        }
        return $version;
    }

    final public function getSupportPage(?string $default = null): ?string
    {
        $composerJson = $this->getComposerJson();

        $homepage = Type::string($composerJson['homepage'] ?? '');
        if ('' === $homepage) {
            return $default;
        }

        if (!preg_match('@^https?://@i', $homepage)) {
            return 'https://' . $homepage;
        }

        return $homepage;
    }

    /**
     * Includes a file in the addon context.
     *
     * @param non-empty-string $file Filename
     * @param array<string, mixed> $context Context values, available as variables in given file
     */
    final public function includeFile(string $file, array $context = []): mixed
    {
        $__file = $file;
        $__context = $context;

        unset($file, $context);

        extract($__context, EXTR_SKIP);

        if (is_file($__path = $this->getPath($__file))) {
            return require $__path;
        }

        if (is_file($__file)) {
            return require $__file;
        }

        throw new RuntimeException(sprintf('Addon "%s": the page path "%s" neither exists as standalone path nor as addon subpath "%s"', $this->name, $__file, $__path));
    }

    /**
     * Adds the addon prefix to the given key and returns the translation for it.
     *
     * @return non-empty-string Translation for the key
     */
    final public function i18n(string $key, string|int ...$replacements): string
    {
        $fullKey = $this->name . '_' . $key;
        if (I18n::hasMsgOrFallback($fullKey)) {
            $key = $fullKey;
        }
        return I18n::msg($key, ...$replacements);
    }

    /** Loads the properties of package.yml. */
    final public function loadProperties(bool $force = false): void
    {
        $file = $this->getPath(self::FILE_PACKAGE);
        if (!is_file($file)) {
            $this->propertiesLoaded = true;
            return;
        }

        /** @var array<string, array{timestamp: int, data: array<string, mixed>}>|null $cache */
        static $cache = null;
        if (null === $cache) {
            /** @var array<string, array{timestamp: int, data: array<string, mixed>}> $cache */
            $cache = File::getCache(Path::coreCache(self::PROPERTIES_CACHE_FILE));
        }
        $id = $this->name;

        if ($force) {
            unset($cache[$id]);
        }

        $isCached = isset($cache[$id]);
        $isBackendAdmin = Core::isBackend() && Core::getUser()?->admin;
        if (!$isCached || (Core::getConsole() || $isBackendAdmin) && $cache[$id]['timestamp'] < filemtime($file)) {
            try {
                $properties = File::getConfig($file);

                $cache[$id]['timestamp'] = filemtime($file);
                $cache[$id]['data'] = $properties;

                /** @var bool $registeredShutdown */
                static $registeredShutdown = false;
                if (!$registeredShutdown) {
                    $registeredShutdown = true;
                    register_shutdown_function(static function () use (&$cache) {
                        foreach ($cache as $addon => $_) {
                            if (!self::exists($addon)) {
                                unset($cache[$addon]);
                            }
                        }
                        File::putCache(Path::coreCache(self::PROPERTIES_CACHE_FILE), $cache);
                    });
                }
            } catch (YamlParseException $exception) {
                if ($this->isInstalled()) {
                    throw $exception;
                }

                $properties = [];
            }
        } else {
            $properties = $cache[$id]['data'];
        }

        $this->properties = array_intersect_key($this->properties, ['install' => null, 'status' => null]);
        if ($properties) {
            foreach ($properties as $key => $value) {
                $key = Type::string($key);
                if (isset($this->properties[$key])) {
                    continue;
                }
                if ('supportpage' !== $key) {
                    $value = I18n::translateArray($value, false, $this->i18n(...));
                } elseif (null !== $value && !preg_match('@^https?://@i', $value)) {
                    $value = 'https://' . $value;
                }
                $this->properties[$key] = $value;
            }
        }
        $this->propertiesLoaded = true;
    }

    final public function getLicense(): ?string
    {
        /** @var string|list<string>|null $license */
        $license = $this->getComposerJson()['license'] ?? null;
        if (is_array($license)) {
            return implode(', ', $license);
        }

        if (is_string($license) && $license) {
            return $license;
        }

        if (is_readable($licenseFile = $this->getPath('LICENSE')) || is_readable($licenseFile = $this->getPath('LICENSE.md'))) {
            $f = fopen($licenseFile, 'r');
            $license = trim(fgets($f) ?: '');
            fclose($f);

            if (preg_match('/^The MIT License(?: \(MIT\))$/i', $license)) {
                return 'MIT';
            }
        }

        return $license ?: null;
    }

    /** Clears the cache of the addon. */
    final public function clearCache(): void
    {
        $cacheDir = $this->getCachePath();
        if (!Dir::delete($cacheDir)) {
            throw new RuntimeException('Addon cache directory "' . $cacheDir . '" is not writable.');
        }

        $cache = File::getCache($path = Path::coreCache(self::PROPERTIES_CACHE_FILE));
        if ($cache) {
            unset($cache[$this->name]);
            File::putCache($path, $cache);
        }

        Extension::registerPoint(new AddonCacheDeleted($this));
    }

    final public function enlist(): void
    {
        $folder = $this->getPath();

        // add addon path for i18n
        if (is_readable($folder . 'lang')) {
            I18n::addDirectory($folder . 'lang');
        }
        // add addon path for fragment loading
        if (is_readable($folder . 'fragments')) {
            Fragment::addDirectory($folder . 'fragments' . DIRECTORY_SEPARATOR);
        }
    }

    /** Boot hook — runs on every request after all addons are enlisted. Override to register listeners etc. */
    public function boot(): void {}

    /**
     * Install hook — runs on install/reinstall. Override for schema/data setup. Must be idempotent.
     *
     * @throws UserMessageException
     */
    public function install(): void {}

    /**
     * Uninstall hook — runs on uninstall. Override for cleanup.
     *
     * @throws UserMessageException
     */
    public function uninstall(): void {}

    /**
     * Returns the registered addons.
     *
     * @return array<non-empty-string, self>
     */
    final public static function getRegisteredAddons(): array
    {
        return self::$addons;
    }

    /**
     * Returns the installed addons.
     *
     * @return array<non-empty-string, self>
     */
    final public static function getInstalledAddons(): array
    {
        return self::filterPackages(self::$addons, 'isInstalled');
    }

    /**
     * Returns the available addons.
     *
     * @return array<non-empty-string, self>
     */
    final public static function getAvailableAddons(): array
    {
        return self::filterPackages(self::$addons, 'isAvailable');
    }

    /**
     * Returns the setup addons.
     *
     * @return array<non-empty-string, self>
     */
    final public static function getSetupAddons(): array
    {
        $addons = [];
        foreach ((array) Core::getProperty('setup_addons', []) as $addon) {
            if (self::exists($addon)) {
                $addons[$addon] = self::require($addon);
            }
        }
        return $addons;
    }

    /** Initializes all addons. */
    final public static function initialize(bool $dbExists = true): void
    {
        if ($dbExists) {
            $config = AddonManager::getAddonConfig();
        } else {
            $config = [];
            foreach (Core::getProperty('setup_addons') as $addon) {
                $config[(string) $addon]['install'] = false;
            }
        }

        $composerPackages = AddonManager::getComposerPackages();
        $addonClasses = null;

        $addons = self::$addons;
        self::$addons = [];
        foreach ($config as $addonName => $addonConfig) {
            if (!isset($composerPackages[$addonName])) {
                continue;
            }

            if (isset($addons[$addonName])) {
                $addon = $addons[$addonName];
            } else {
                $class = $addonConfig['class'] ?? null;
                if (!is_string($class) || !is_subclass_of($class, self::class)) {
                    // bootstrap fallback: config has no (valid) class — e.g. fresh setup, brand-new addon,
                    // class rename after composer update without sync. Sync will refresh the config.
                    $addonClasses ??= AddonManager::getAddonClasses();
                    if (!isset($addonClasses[$addonName])) {
                        throw new RuntimeException(sprintf('Addon "%s" must declare its addon class via composer.json `extra.redaxo.addon-class`.', $addonName));
                    }
                    $class = $addonClasses[$addonName];
                }
                $addon = new $class($composerPackages[$addonName], $addonName);
            }
            $addon->setProperty('install', $addonConfig['install'] ?? false);
            $addon->setProperty('status', $addonConfig['status'] ?? false);
            self::$addons[$addonName] = $addon;
        }
    }

    /**
     * Returns the parsed composer.json of the addon.
     *
     * @return array<string, mixed>
     */
    final public function getComposerJson(): array
    {
        if (null !== $this->composerJson) {
            return $this->composerJson;
        }

        $json = File::get($this->getPath('composer.json'));
        if (!$json) {
            return $this->composerJson = [];
        }

        /** @var array<string, mixed> $composerJson */
        $composerJson = Type::array(json_decode($json, true, flags: JSON_THROW_ON_ERROR));

        return $this->composerJson = $composerJson;
    }

    /**
     * Filters addons by the given method.
     *
     * @param array<non-empty-string, self> $addons Array of addons
     * @param string $method A Addon method
     * @return array<non-empty-string, Addon>
     */
    private static function filterPackages(array $addons, string $method): array
    {
        return array_filter($addons, static function (Addon $addon) use ($method): bool {
            $return = $addon->$method();
            assert(is_bool($return));

            return $return;
        });
    }
}
