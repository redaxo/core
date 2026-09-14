<?php

namespace Redaxo\Core\Addon;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use OutOfBoundsException;
use Redaxo\Core\Addon\ExtensionPoint\AddonCacheDeleted;
use Redaxo\Core\Backend\Page;
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
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;

use function array_flip;
use function array_keys;
use function array_merge;
use function filemtime;
use function filesize;
use function function_exists;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function sprintf;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const EXTR_SKIP;
use const JSON_THROW_ON_ERROR;

abstract class Addon
{
    /**
     * Array of all addons.
     *
     * @var array<non-empty-string, self>
     */
    private static array $addons = [];

    /** @var list<non-empty-string> */
    private static array $bootOrder = [];

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
     * Default config values applied on install (only for keys that are not already set). Override to provide defaults.
     *
     * @var array<string, mixed>
     */
    public protected(set) array $defaultConfig = [];

    /**
     * Properties.
     *
     * @var array<string, mixed>
     */
    private array $properties = [];

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
        return isset($this->properties[$key]);
    }

    /** @param non-empty-string $key */
    final public function removeProperty(string $key): void
    {
        unset($this->properties[$key]);
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
        $version = InstalledVersions::getPrettyVersion($this->package) ?? '';

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

        Extension::dispatch(new AddonCacheDeleted($this));
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
     * Backend page hook — override to register the addon's backend pages.
     *
     * Runs only in the backend, after the core pages and all earlier-loading addons have been registered, so
     * Controller::getPageObject() can be used to attach subpages to existing core or addon pages. Top-level pages
     * are typically MainPage instances (shown in the navigation); a plain Page can be used for hidden entry points
     * that are reachable by key/URL but should not appear in the navigation. Any page without an explicit path falls
     * back to the convention `pages/<key>.php` (or `pages/index.php` for the main page whose key equals the addon name).
     *
     * @return iterable<Page>
     */
    public function getPages(): iterable
    {
        return [];
    }

    /**
     * Install hook — runs on install/reinstall. Override for schema/data setup. Must be idempotent.
     *
     * @throws UserMessageException to abort the installation with a message
     */
    public function install(): void {}

    /**
     * Uninstall hook — runs on uninstall. Override for cleanup.
     *
     * @throws UserMessageException to abort the uninstallation with a message
     */
    public function uninstall(): void {}

    /**
     * Returns all addons, i.e. all composer packages of type `redaxo-addon`.
     *
     * @return array<non-empty-string, self>
     */
    final public static function getAddons(): array
    {
        return self::$addons;
    }

    /**
     * Returns the addon names in boot order.
     *
     * @return list<non-empty-string>
     */
    final public static function getBootOrder(): array
    {
        return self::$bootOrder;
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
    final public static function initialize(): void
    {
        $cache = self::loadCache();
        $classes = $cache['classes'] ?? AddonManager::getAddonClasses();

        $addons = self::$addons;
        self::$addons = [];
        foreach (AddonManager::getComposerPackages() as $addonName => $package) {
            if (!isset($classes[$addonName])) {
                throw new RuntimeException(sprintf('Addon "%s" must declare its addon class via composer.json `extra.redaxo.addon-class`.', $addonName));
            }

            $class = $classes[$addonName];
            $addon = $addons[$addonName] ?? null;

            self::$addons[$addonName] = $addon instanceof $class ? $addon : new $class($package, $addonName);
        }

        self::$bootOrder = $cache['order'] ?? self::generateBootOrder();

        if (null === $cache) {
            self::saveCache($classes, self::$bootOrder);
        }
    }

    /**
     * Generates the boot order: addons marked as early first, then the ones with normal load order sorted so
     * that an addon boots after the addons it requires, then the ones marked as late.
     *
     * @return list<non-empty-string>
     */
    private static function generateBootOrder(): array
    {
        /** @var list<non-empty-string> $early */
        $early = [];
        /** @var list<string> $normal */
        $normal = [];
        /** @var list<non-empty-string> $late */
        $late = [];
        /** @var array<non-empty-string, array<non-empty-string, true>> $requires */
        $requires = [];

        $add = static function (string $id) use (&$add, &$normal, &$requires): void {
            $normal[] = $id;
            unset($requires[$id]);
            foreach ($requires as $rp => &$ps) {
                unset($ps[$id]);
                if ([] === $ps) {
                    $add($rp);
                }
            }
        };

        foreach (self::$addons as $id => $addon) {
            if (LoadOrder::Early === $addon->load) {
                $early[] = $id;
            } elseif (LoadOrder::Late === $addon->load) {
                $late[] = $id;
            } else {
                foreach (self::getRequiredAddons($addon) as $addonId) {
                    if (!in_array($addonId, $normal) && !in_array(self::get($addonId)?->load, [LoadOrder::Early, LoadOrder::Late], true)) {
                        $requires[$id][$addonId] = true;
                    }
                }
                if (!isset($requires[$id])) {
                    $add($id);
                }
            }
        }

        /** @var list<non-empty-string> */
        return array_merge($early, $normal, array_keys($requires), $late);
    }

    /**
     * Returns the names of the addons that the given addon requires via composer.json.
     *
     * @return list<non-empty-string>
     */
    private static function getRequiredAddons(self $addon): array
    {
        /** @var array<string, mixed> $require */
        $require = Type::array($addon->getComposerJson()['require'] ?? []);
        if (!$require) {
            return [];
        }

        $addonsByPackage = array_flip(AddonManager::getComposerPackages());

        $requiredAddons = [];
        foreach (array_keys($require) as $packageName) {
            if (isset($addonsByPackage[$packageName])) {
                $requiredAddons[] = $addonsByPackage[$packageName];
            }
        }

        return $requiredAddons;
    }

    /**
     * Both the addon classes and the boot order derive from the composer installation, and building them means
     * parsing composer's `installed.json` and the composer.json of every addon. So they are cached in a
     * generated PHP file, which is rebuilt whenever composer wrote a new `installed.json`. Editing an addon's
     * composer.json without running composer therefore requires a cache clear.
     *
     * @return array{classes: array<non-empty-string, class-string<self>>, order: list<non-empty-string>}|null
     */
    private static function loadCache(): ?array
    {
        $file = Path::coreCache('addons.php');
        if (!is_file($file)) {
            return null;
        }

        /** @var mixed $cache */
        $cache = include $file;

        if (!is_array($cache) || ($cache['key'] ?? null) !== self::getComposerStateKey()) {
            return null;
        }

        /** @var array{classes: array<non-empty-string, class-string<self>>, order: list<non-empty-string>} */
        return ['classes' => $cache['classes'], 'order' => $cache['order']];
    }

    /**
     * @param array<non-empty-string, class-string<self>> $classes
     * @param list<non-empty-string> $order
     */
    private static function saveCache(array $classes, array $order): void
    {
        $file = Path::coreCache('addons.php');

        File::put($file, '<?php' . "\n\n" . 'return ' . var_export([
            'key' => self::getComposerStateKey(),
            'classes' => $classes,
            'order' => $order,
        ], true) . ';' . "\n");

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /** Identifies the current composer installation, so that the cache is rebuilt after every composer run. */
    private static function getComposerStateKey(): string
    {
        $parts = [];

        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $_loader) {
            $file = $vendorDir . '/composer/installed.json';
            if (is_file($file)) {
                $parts[] = $file . ':' . (int) filemtime($file) . ':' . (int) filesize($file);
            }
        }

        return hash('xxh128', implode("\n", $parts));
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
}
