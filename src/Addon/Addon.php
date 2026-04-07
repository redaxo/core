<?php

namespace Redaxo\Core\Addon;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Override;
use Redaxo\Core\Addon\ExtensionPoint\AddonCacheDeleted;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
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

final class Addon implements AddonInterface
{
    public const string FILE_PACKAGE = 'package.yml';
    public const string FILE_BOOT = 'boot.php';
    public const string FILE_INSTALL = 'install.php';
    public const string FILE_INSTALL_SQL = 'install.sql';
    public const string FILE_UNINSTALL = 'uninstall.php';
    public const string FILE_UNINSTALL_SQL = 'uninstall.sql';
    public const string FILE_UPDATE = 'update.php';

    private const string PROPERTIES_CACHE_FILE = 'packages.cache';

    /**
     * Array of all addons.
     *
     * @var array<non-empty-string, self>
     */
    private static array $addons = [];

    /** @var non-empty-string */
    public string $path {
        get {
            if (isset($this->path)) {
                return $this->path;
            }

            try {
                return $this->path = realpath(InstalledVersions::getInstallPath($this->name));
            } catch (OutOfBoundsException) {
                return $this->path = realpath(InstalledVersions::getRootPackage()['install_path']) . '/vendor/' . $this->package;
            }
        }
    }

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

    private function __construct(
        /** @var non-empty-string Composer package name */
        public readonly string $package,
        /** @var non-empty-string Name of the addon */
        public readonly string $name,
    ) {}

    /**
     * Returns the addon by the given name.
     *
     * @param string $addon Addon name
     * @return AddonInterface If the addon exists, a `Addon` is returned, otherwise a `NullAddon`
     */
    public static function get(string $addon): AddonInterface
    {
        if (!isset(self::$addons[$addon])) {
            return NullAddon::getInstance();
        }

        return self::$addons[$addon];
    }

    /**
     * Returns the addon by the given name.
     *
     * @psalm-assert =non-empty-string $addon
     */
    public static function require(string $addon): self
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
    public static function exists(string $addon): bool
    {
        return isset(self::$addons[$addon]);
    }

    #[Override]
    public function getPath(string $file = ''): string
    {
        return Path::addon($this->name, $file);
    }

    #[Override]
    public function getAssetsPath(string $file = ''): string
    {
        return Path::addonAssets($this->name, $file);
    }

    #[Override]
    public function getAssetsUrl(string $file = ''): string
    {
        return Url::addonAssets($this->name, $file);
    }

    #[Override]
    public function getDataPath(string $file = ''): string
    {
        return Path::addonData($this->name, $file);
    }

    #[Override]
    public function getCachePath(string $file = ''): string
    {
        return Path::addonCache($this->name, $file);
    }

    #[Override]
    public function setConfig(string|array $key, mixed $value = null): bool
    {
        return Config::set($this->name, $key, $value);
    }

    #[Override]
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return Config::get($this->name, $key, $default);
    }

    #[Override]
    public function hasConfig(?string $key = null): bool
    {
        return Config::has($this->name, $key);
    }

    #[Override]
    public function removeConfig(string $key): bool
    {
        return Config::remove($this->name, $key);
    }

    #[Override]
    public function setProperty(string $key, mixed $value): void
    {
        $this->properties[$key] = $value;
    }

    #[Override]
    public function getProperty(string $key, mixed $default = null): mixed
    {
        if ($this->hasProperty($key)) {
            return $this->properties[$key];
        }
        return $default;
    }

    #[Override]
    public function hasProperty(string $key): bool
    {
        if (!isset($this->properties[$key]) && !$this->propertiesLoaded) {
            $this->loadProperties();
        }
        return isset($this->properties[$key]);
    }

    #[Override]
    public function removeProperty(string $key): void
    {
        unset($this->properties[$key]);
    }

    #[Override]
    public function isAvailable(): bool
    {
        return $this->isInstalled() && (bool) $this->getProperty('status', false);
    }

    #[Override]
    public function isInstalled(): bool
    {
        return (bool) $this->getProperty('install', false);
    }

    #[Override]
    public function getAuthor(?string $default = null): ?string
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

    #[Override]
    public function getVersion(?string $format = null): string
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

    #[Override]
    public function getSupportPage(?string $default = null): ?string
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

    #[Override]
    public function includeFile(string $file, array $context = []): mixed
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

    #[Override]
    public function i18n(string $key, string|int ...$replacements): string
    {
        $fullKey = $this->name . '_' . $key;
        if (I18n::hasMsgOrFallback($fullKey)) {
            $key = $fullKey;
        }
        return I18n::msg($key, ...$replacements);
    }

    /** Loads the properties of package.yml. */
    public function loadProperties(bool $force = false): void
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
        $isBackendAdmin = Core::isBackend() && Core::getUser()?->isAdmin();
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

    public function getLicense(): ?string
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
    public function clearCache(): void
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

    public function enlist(): void
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

    public function boot(): void
    {
        if (is_readable($this->getPath(self::FILE_BOOT))) {
            $this->includeFile(self::FILE_BOOT);
        }
    }

    /**
     * Returns the registered addons.
     *
     * @return array<non-empty-string, self>
     */
    public static function getRegisteredAddons(): array
    {
        return self::$addons;
    }

    /**
     * Returns the installed addons.
     *
     * @return array<non-empty-string, self>
     */
    public static function getInstalledAddons(): array
    {
        return self::filterPackages(self::$addons, 'isInstalled');
    }

    /**
     * Returns the available addons.
     *
     * @return array<non-empty-string, self>
     */
    public static function getAvailableAddons(): array
    {
        return self::filterPackages(self::$addons, 'isAvailable');
    }

    /**
     * Returns the setup addons.
     *
     * @return array<non-empty-string, self>
     */
    public static function getSetupAddons(): array
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
    public static function initialize(bool $dbExists = true): void
    {
        if ($dbExists) {
            $config = Core::getPackageConfig();
        } else {
            $config = [];
            foreach (Core::getProperty('setup_addons') as $addon) {
                $config[(string) $addon]['install'] = false;
            }
        }

        $composerPackages = AddonManager::getComposerPackages();

        $addons = self::$addons;
        self::$addons = [];
        foreach ($config as $addonName => $addonConfig) {
            if (!isset($composerPackages[$addonName])) {
                continue;
            }

            $addon = $addons[$addonName] ?? new self($composerPackages[$addonName], $addonName);
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
    public function getComposerJson(): array
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
