<?php

namespace Redaxo\Core\Addon;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Exception\YamlParseException;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

use function in_array;
use function is_array;
use function is_string;
use function sprintf;

use const JSON_THROW_ON_ERROR;

class AddonManager
{
    use FactoryTrait;

    protected bool $generatePackageOrder = true;
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
            // check package.yml
            $addonFile = $this->addon->getPath(Addon::FILE_PACKAGE);
            try {
                File::getConfig($addonFile);
            } catch (YamlParseException $e) {
                throw new UserMessageException($this->i18n('invalid_yml_file') . ' ' . $e->getMessage());
            }

            // check requirements and conflicts
            $message = '';
            if (!$this->checkRequirements()) {
                $message = $this->message;
            }
            if ($message) {
                throw new UserMessageException($message);
            }

            $reinstall = $this->addon->getProperty('install');
            $this->addon->setProperty('install', true);

            I18n::addDirectory($this->addon->getPath('lang'));

            // run install hook
            $this->addon->install();
            $successMessage = (string) $this->addon->getProperty('successmsg', '');

            /** @psalm-taint-escape html */
            $instmsg = (string) $this->addon->getProperty('installmsg', '');
            if ('' != $instmsg) {
                throw new UserMessageException($instmsg);
            }
            if (!$this->addon->isInstalled()) {
                throw new UserMessageException($this->i18n('no_reason'));
            }

            if (!$reinstall) {
                $this->addon->setProperty('status', true);
            }
            static::saveConfig();
            if ($this->generatePackageOrder) {
                self::generatePackageOrder();
            }

            foreach ($this->addon->getProperty('default_config', []) as $key => $value) {
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

            $this->message = $this->i18n($reinstall ? 'reinstalled' : 'installed', $this->addon->name);
            if ($successMessage) {
                $this->message .= ' ' . $successMessage;
            }

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->addon->setProperty('install', false);
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
        $isActivated = $this->addon->isAvailable();
        if ($isActivated && !$this->deactivate()) {
            return false;
        }

        try {
            $this->addon->setProperty('install', false);

            if (!$isActivated) {
                I18n::addDirectory($this->addon->getPath('lang'));
            }

            // run uninstall hook
            $this->addon->uninstall();

            /** @psalm-taint-escape html */
            $instmsg = (string) $this->addon->getProperty('installmsg', '');
            if ('' != $instmsg) {
                throw new UserMessageException($instmsg);
            }
            if ($this->addon->isInstalled()) {
                throw new UserMessageException($this->i18n('no_reason'));
            }

            // delete assets
            $assets = $this->addon->getAssetsPath();
            if (is_dir($assets) && !Dir::delete($assets)) {
                throw new UserMessageException($this->i18n('install_cant_delete_files'));
            }

            // clear cache of addon
            $this->addon->clearCache();

            Config::removeNamespace($this->addon->name);

            static::saveConfig();
            $this->message = $this->i18n('uninstalled', $this->addon->name);

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        }

        $this->addon->setProperty('install', true);
        if ($isActivated) {
            $this->addon->setProperty('status', true);
        }
        static::saveConfig();
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
        if ($this->addon->isInstalled()) {
            $state = '';
            if (!$this->checkRequirements()) {
                $state .= $this->message;
            }
            $state = $state ?: true;

            if (true === $state) {
                $this->addon->setProperty('status', true);
                static::saveConfig();
            }
            if (true === $state && $this->generatePackageOrder) {
                self::generatePackageOrder();
            }
        } else {
            $state = $this->i18n('not_installed', $this->addon->name);
        }

        if (true !== $state) {
            // error while config generation, rollback addon status
            $this->addon->setProperty('status', false);
            $this->message = $this->i18n('no_activation', $this->addon->name) . '<br />' . $state;
            return false;
        }

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
        $state = $this->checkDependencies();

        if ($state) {
            $this->addon->setProperty('status', false);
            static::saveConfig();

            // clear cache of addon
            $this->addon->clearCache();

            if ($this->generatePackageOrder) {
                self::generatePackageOrder();
            }

            $this->message = $this->i18n('deactivated', $this->addon->name);
            return true;
        }

        $this->message = $this->i18n('no_deactivation', $this->addon->name) . '<br />' . $this->message;
        return false;
    }

    /** Cleans up a addon that was removed from the filesystem. */
    protected function _delete(): void
    {
        if ($this->addon->isInstalled()) {
            $this->uninstall();
        }

        $this->addon->clearCache();
    }

    /** Checks whether the required addons are available. */
    public function checkRequirements(): bool
    {
        $state = [];

        foreach (self::getRequiredAddons($this->addon) as $addonName) {
            if (Addon::get($addonName)?->isAvailable()) {
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

        foreach (Addon::getAvailableAddons() as $addon) {
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

    /** Generates the addon order. */
    public static function generatePackageOrder(): void
    {
        /** @var list<string> $early */
        $early = [];
        /** @var list<string> $normal */
        $normal = [];
        /** @var list<string> $late */
        $late = [];
        /** @var array<string, array<string, true>> $requires */
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
        foreach (Addon::getAvailableAddons() as $addon) {
            $id = $addon->name;
            $load = $addon->getProperty('load');
            if ('early' === $load) {
                $early[] = $id;
            } elseif ('late' === $load) {
                $late[] = $id;
            } else {
                foreach (self::getRequiredAddons($addon) as $addonId) {
                    if (!in_array($addonId, $normal) && !in_array(Addon::get($addonId)?->getProperty('load'), ['early', 'late'])) {
                        $requires[$id][$addonId] = true;
                    }
                }
                if (!isset($requires[$id])) {
                    $add($id);
                }
            }
        }
        Core::setConfig('package-order', array_merge($early, $normal, array_keys($requires), $late));
    }

    /** Saves the addon config. */
    protected static function saveConfig(): void
    {
        $config = [];
        foreach (Addon::getRegisteredAddons() as $addonName => $addon) {
            $config[$addonName]['class'] = $addon::class;
            $config[$addonName]['install'] = $addon->isInstalled();
            $config[$addonName]['status'] = $addon->isAvailable();
        }
        Core::setConfig('package-config', $config);
    }

    /** Synchronizes the addons with the file system. */
    public static function synchronizeWithFileSystem(): void
    {
        $config = Core::getPackageConfig();
        $registeredAddons = Addon::getRegisteredAddons();
        $packages = self::getComposerPackages();
        $addonClasses = self::getAddonClasses();

        foreach (array_diff_key($registeredAddons, $packages) as $addonName => $addon) {
            $manager = self::factory($addon);
            $manager->_delete();
            unset($config[$addonName]);
        }
        foreach ($packages as $addonName => $package) {
            if (!isset($addonClasses[$addonName])) {
                throw new RuntimeException(sprintf('Addon "%s" must declare its addon class via composer.json `extra.redaxo.addon-class`.', $addonName));
            }
            $config[$addonName]['class'] = $addonClasses[$addonName];
            if (!Addon::exists($addonName)) {
                $config[$addonName]['install'] = false;
                $config[$addonName]['status'] = false;
            } else {
                $addon = Addon::require($addonName);
                $config[$addonName]['install'] = $addon->isInstalled();
                $config[$addonName]['status'] = $addon->isAvailable();
            }
        }
        ksort($config);

        Core::setConfig('package-config', $config);
        Addon::initialize();
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
