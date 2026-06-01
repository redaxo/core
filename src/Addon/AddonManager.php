<?php

namespace Redaxo\Core\Addon;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Psr\Log\LogLevel;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

use function in_array;
use function is_array;
use function is_string;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * @phpstan-type TAddonConfig array<non-empty-string, array{class: class-string<Addon>, state: value-of<AddonState>}>
 * @phpstan-type TAddonOrder list<non-empty-string>
 */
class AddonManager
{
    use FactoryTrait;

    /** @var array{config: TAddonConfig, order: TAddonOrder}|null */
    private static ?array $addonsData = null;

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
     * Installs a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function install(): bool
    {
        try {
            // check requirements and conflicts
            $message = '';
            if (!$this->checkRequirements()) {
                $message = $this->message;
            }
            if ($message) {
                throw new UserMessageException($message);
            }

            $reinstall = $this->addon->isInstalled();

            I18n::addDirectory($this->addon->getPath('lang'));

            // run install hook (can only abort by throwing a UserMessageException)
            $this->addon->install();
            $successMessage = (string) $this->addon->getProperty('successmsg', '');

            foreach ($this->addon->defaultConfig as $key => $value) {
                if (!$this->addon->hasConfig($key)) {
                    $this->addon->setConfig($key, $value);
                }
            }

            // copy assets
            $assets = $this->addon->getPath('assets');
            if (is_dir($assets)) {
                if (!Dir::copy($assets, $this->addon->getAssetsPath())) {
                    throw new UserMessageException($this->i18n('install_cant_copy_files'));
                }
            }

            // everything succeeded — commit (fresh installs are activated right away)
            if (!$reinstall) {
                $this->addon->setState(AddonState::Activated);
            }
            static::saveConfig();
            self::generateAddonOrder();

            $this->message = $this->i18n($reinstall ? 'reinstalled' : 'installed', $this->addon->name);
            if ($successMessage) {
                $this->message .= ' ' . $successMessage;
            }

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->message = $this->i18n('no_install', $this->addon->name) . '<br />' . $this->message;

        return false;
    }

    /**
     * Uninstalls a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function uninstall(): bool
    {
        $originalState = $this->addon->state;
        $isActivated = $this->addon->isActivated();
        if ($isActivated && !$this->deactivate()) {
            return false;
        }

        try {
            if (!$isActivated) {
                I18n::addDirectory($this->addon->getPath('lang'));
            }

            // run uninstall hook (can only abort by throwing a UserMessageException)
            $this->addon->uninstall();

            // delete assets
            $assets = $this->addon->getAssetsPath();
            if (is_dir($assets) && !Dir::delete($assets)) {
                throw new UserMessageException($this->i18n('install_cant_delete_files'));
            }

            // clear cache of addon
            $this->addon->clearCache();

            Config::removeNamespace($this->addon->name);

            // everything succeeded — commit the new state
            $this->addon->setState(AddonState::Uninstalled);
            static::saveConfig();
            $this->message = $this->i18n('uninstalled', $this->addon->name);

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        if ($isActivated) {
            // the deactivation was already committed — restore the previous state
            $this->addon->setState($originalState);
            static::saveConfig();
            self::generateAddonOrder();
        }
        $this->message = $this->i18n('no_uninstall', $this->addon->name) . '<br />' . $this->message;

        return false;
    }

    /**
     * Activates a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function activate(): bool
    {
        if (!$this->addon->isInstalled()) {
            $this->message = $this->i18n('no_activation', $this->addon->name) . '<br />' . $this->i18n('not_installed', $this->addon->name);
            return false;
        }

        if (!$this->checkRequirements()) {
            $this->message = $this->i18n('no_activation', $this->addon->name) . '<br />' . $this->message;
            return false;
        }

        $this->addon->setState(AddonState::Activated);
        static::saveConfig();
        self::generateAddonOrder();

        $this->message = $this->i18n('activated', $this->addon->name);
        return true;
    }

    /**
     * Deactivates a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function deactivate(): bool
    {
        if ($this->checkDependencies()) {
            $this->addon->setState(AddonState::Installed);
            static::saveConfig();

            // clear cache of addon
            $this->addon->clearCache();

            self::generateAddonOrder();

            $this->message = $this->i18n('deactivated', $this->addon->name);
            return true;
        }

        $this->message = $this->i18n('no_deactivation', $this->addon->name) . '<br />' . $this->message;
        return false;
    }

    /**
     * Cleans up the leftovers of an addon whose code was removed from the filesystem.
     *
     * Only the parts reachable by name are removed (assets, cache, config). The addon's own uninstall hook
     * can no longer run because its code is gone, so anything it created itself (e.g. database tables) is
     * left behind — to fully uninstall an addon, uninstall it before removing it via composer.
     *
     * @param non-empty-string $addon
     */
    private static function clearLeftovers(string $addon): void
    {
        Dir::delete(Path::addonAssets($addon));
        Dir::delete(Path::addonCache($addon));
        Config::removeNamespace($addon);
    }

    /** Checks whether the required addons are available. */
    public function checkRequirements(): bool
    {
        $state = [];

        foreach (self::getRequiredAddons($this->addon) as $addonName) {
            if (Addon::get($addonName)?->isActivated()) {
                continue;
            }

            $jumpPackageUrl = '#package-' . Str::normalize($addonName, '-', '_');
            if ('packages' !== Controller::getCurrentPage()) {
                $jumpPackageUrl = Url::backendPage('packages') . $jumpPackageUrl;
            }

            $state[] = $this->i18n('requirement_error_addon', $addonName) . ' <a href="' . $jumpPackageUrl . '"><i class="rex-icon fa-arrow-circle-right" title="' . $this->i18n('jump_to', $addonName) . '"></i></a>';
        }

        if (empty($state)) {
            return true;
        }
        $this->message = implode('<br />', $state);
        return false;
    }

    /** Checks if another addon which is activated, depends on the given package. */
    public function checkDependencies(): bool
    {
        $i18nPrefix = 'package_dependencies_error_';
        $state = [];

        foreach (Addon::getActivatedAddons() as $addon) {
            if ($addon === $this->addon) {
                continue;
            }

            if (in_array($this->addon->name, self::getRequiredAddons($addon))) {
                $state[] = I18n::msg($i18nPrefix . 'addon', $addon->name);
            }
        }

        if (empty($state)) {
            return true;
        }
        $this->message = implode('<br />', $state);
        return false;
    }

    /**
     * Translates the given key.
     *
     * @param string $key Key
     *
     * @return string Tranlates text
     */
    protected function i18n(string $key, string|int ...$replacements): string
    {
        $fullKey = 'addon_' . $key;
        if (!I18n::hasMsg($fullKey)) {
            $fullKey = 'package_' . $key;
        }

        return I18n::msg($fullKey, ...$replacements);
    }

    /** @return TAddonConfig */
    public static function getAddonConfig(): array
    {
        return self::loadAddonsData()['config'];
    }

    /** @return TAddonOrder */
    public static function getAddonOrder(): array
    {
        // The persisted order may list addons that are currently not available (e.g. a dev-only addon
        // missing after `composer install --no-dev`). Filter them out so boot does not require a missing
        // addon, while leaving the stored order untouched so it returns unchanged once available again.
        return array_values(array_filter(self::loadAddonsData()['order'], Addon::exists(...)));
    }

    /** Generates the addon order. */
    public static function generateAddonOrder(): void
    {
        /** @var list<non-empty-string> $early */
        $early = [];
        /** @var list<non-empty-string> $normal */
        $normal = [];
        /** @var list<non-empty-string> $late */
        $late = [];
        /** @var array<non-empty-string, array<non-empty-string, true>> $requires */
        $requires = [];

        $add = static function ($id) use (&$add, &$normal, &$requires) {
            $normal[] = $id;
            unset($requires[$id]);
            foreach ($requires as $rp => &$ps) {
                unset($ps[$id]);
                if (empty($ps)) {
                    $add($rp);
                }
            }
        };
        foreach (Addon::getActivatedAddons() as $addon) {
            $id = $addon->name;
            if (LoadOrder::Early === $addon->load) {
                $early[] = $id;
            } elseif (LoadOrder::Late === $addon->load) {
                $late[] = $id;
            } else {
                foreach (self::getRequiredAddons($addon) as $addonId) {
                    if (!in_array($addonId, $normal) && !in_array(Addon::get($addonId)?->load, [LoadOrder::Early, LoadOrder::Late], true)) {
                        $requires[$id][$addonId] = true;
                    }
                }
                if (!isset($requires[$id])) {
                    $add($id);
                }
            }
        }

        /** @var TAddonOrder $order */
        $order = array_merge($early, $normal, array_keys($requires), $late);
        self::saveAddonsData(order: $order);
    }

    /** Saves the addon config. */
    protected static function saveConfig(): void
    {
        // Start from the existing config so entries of addons that are configured but currently not loaded
        // (e.g. a dev-only addon missing after `composer install --no-dev`) are preserved. Removing orphaned
        // entries is the dedicated job of synchronizeWithFileSystem().
        $config = self::getAddonConfig();
        foreach (Addon::getRegisteredAddons() as $addonName => $addon) {
            $config[$addonName] = ['class' => $addon::class, 'state' => $addon->state->value];
        }

        self::saveAddonsData(config: $config);
    }

    /** Synchronizes the addons with the file system. */
    public static function synchronizeWithFileSystem(): void
    {
        // Whether composer was installed with dev dependencies. In a production install (`--no-dev`) a
        // dev-only addon is intentionally absent, so its config entry must be preserved.
        $devMode = self::isDevInstall();

        $config = self::getAddonConfig();
        $packages = self::getComposerPackages();
        $addonClasses = self::getAddonClasses();
        $removed = false;

        // Addons that have a config entry but are no longer a composer package.
        foreach (array_diff_key($config, $packages) as $addonName => $addonConfig) {
            if (!$devMode) {
                // Production install: the addon is intentionally absent (dev-only addon). Keep its config
                // entry untouched so it returns unchanged once dev dependencies are installed again.
                continue;
            }

            // Dev install: the addon was genuinely removed (`composer remove`). Its code is gone, so its own
            // uninstall hook can no longer run — clean up everything reachable by name and forget the addon.
            if (AddonState::Uninstalled->value !== ($addonConfig['state'] ?? AddonState::Uninstalled->value)) {
                Logger::factory()->log(LogLevel::WARNING, sprintf(
                    'Addon "%s" was removed from the filesystem while still installed. Its assets, cache and config have been cleaned up, but its uninstall routine (e.g. database changes) could not run because its code is gone. To fully uninstall an addon, uninstall it before removing it via composer.',
                    $addonName,
                ));
            }

            self::clearLeftovers($addonName);
            unset($config[$addonName]);
            $removed = true;
        }
        foreach ($packages as $addonName => $package) {
            if (!isset($addonClasses[$addonName])) {
                throw new RuntimeException(sprintf('Addon "%s" must declare its addon class via composer.json `extra.redaxo.addon-class`.', $addonName));
            }
            $config[$addonName]['class'] = $addonClasses[$addonName];
            if (!Addon::exists($addonName)) {
                $config[$addonName]['state'] = AddonState::Uninstalled->value;
            } else {
                $config[$addonName]['state'] = Addon::require($addonName)->state->value;
            }
        }

        self::saveAddonsData(config: $config);
        Addon::initialize();

        if ($removed) {
            // Drop the removed addons from the persisted order as well, so the committed addons.json stays clean.
            self::generateAddonOrder();
        }
    }

    /**
     * Returns whether composer was installed with dev dependencies (i.e. not `--no-dev`).
     *
     * {@see InstalledVersions::getRootPackage()} is unreliable here: a dependency that ships a scoped vendor
     * with its own autoloader (e.g. rector) can become the reported root package. So instead we look through
     * all install data sets for the one that actually contains redaxo/core and read its root dev flag.
     */
    private static function isDevInstall(): bool
    {
        foreach (InstalledVersions::getAllRawData() as $data) {
            if ('redaxo/core' === ($data['root']['name'] ?? null) || isset($data['versions']['redaxo/core'])) {
                return (bool) ($data['root']['dev'] ?? false);
            }
        }

        return false;
    }

    /** @return array{config: TAddonConfig, order: TAddonOrder} */
    private static function loadAddonsData(): array
    {
        if (null !== self::$addonsData) {
            return self::$addonsData;
        }

        $file = Path::coreData('addons.json');
        if (!is_file($file)) {
            return self::$addonsData = ['config' => [], 'order' => []];
        }

        /** @var array{config: TAddonConfig, order: TAddonOrder} $data */
        $data = File::getCache($file) ?? ['config' => [], 'order' => []];

        return self::$addonsData = $data;
    }

    /**
     * @param TAddonConfig|null $config
     * @param TAddonOrder|null $order
     */
    private static function saveAddonsData(?array $config = null, ?array $order = null): void
    {
        $data = self::loadAddonsData();

        if (null !== $config) {
            // keep the config sorted by addon name so the committed addons.json stays stable (the boot order
            // is tracked separately in $data['order'])
            ksort($config);
            $data['config'] = $config;
        }
        if (null !== $order) {
            $data['order'] = $order;
        }

        self::$addonsData = $data;
        File::put(Path::coreData('addons.json'), (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Returns the addon-class mapping for all registered `redaxo-addon` packages, read from `vendor/composer/installed.json`.
     *
     * Used by setup and sync to write the resolved classes into the package-config. Normal request boots
     * read the class directly from the package-config and never call this.
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

    /**
     * Returns the addon names that the given addon requires via composer.json.
     *
     * @return list<string>
     */
    private static function getRequiredAddons(Addon $addon): array
    {
        /** @var array<string, array<mixed>> $require */
        $require = Type::array($addon->getComposerJson()['require'] ?? []);
        if (!$require) {
            return [];
        }

        $addonPackages = self::getComposerPackages();
        $addonsByPackage = array_flip($addonPackages);

        $requiredAddons = [];
        foreach (array_keys($require) as $packageName) {
            if (isset($addonsByPackage[$packageName])) {
                $requiredAddons[] = $addonsByPackage[$packageName];
            }
        }

        return $requiredAddons;
    }
}
