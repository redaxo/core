<?php

namespace Redaxo\Core\Addon;

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Exception\YamlParseException;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Version;

use function extension_loaded;
use function in_array;
use function is_array;
use function is_string;

use const PHP_VERSION;

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
     * @param bool $installDump When TRUE, the sql dump will be importet
     * @return bool TRUE on success, FALSE on error
     */
    public function install(bool $installDump = true): bool
    {
        try {
            // check package directory perms
            $installDir = $this->addon->getPath();
            if (!Dir::isWritable($installDir)) {
                throw new UserMessageException($this->i18n('dir_not_writable', $installDir));
            }

            // check package.yml
            $addonFile = $this->addon->getPath(Addon::FILE_PACKAGE);
            if (!is_readable($addonFile)) {
                throw new UserMessageException($this->i18n('missing_yml_file'));
            }
            try {
                File::getConfig($addonFile);
            } catch (YamlParseException $e) {
                throw new UserMessageException($this->i18n('invalid_yml_file') . ' ' . $e->getMessage());
            }
            $addonId = $this->addon->getProperty('package');
            if (null === $addonId) {
                throw new UserMessageException($this->i18n('missing_id', $this->addon->getPackageId()));
            }
            if ($addonId != $this->addon->getPackageId()) {
                throw new UserMessageException($this->wrongPackageId($addonId));
            }
            if (null === $this->addon->getProperty('version')) {
                throw new UserMessageException($this->i18n('missing_version'));
            }

            // check requirements and conflicts
            $message = '';
            if (!$this->checkRequirements()) {
                $message = $this->message;
            }
            if (!$this->checkConflicts()) {
                $message .= $this->message;
            }
            if ($message) {
                throw new UserMessageException($message);
            }

            $reinstall = $this->addon->getProperty('install');
            $this->addon->setProperty('install', true);

            I18n::addDirectory($this->addon->getPath('lang'));

            // include install.php
            $successMessage = '';
            if (is_readable($this->addon->getPath(Addon::FILE_INSTALL))) {
                $this->addon->includeFile(Addon::FILE_INSTALL);
                $successMessage = $this->addon->getProperty('successmsg', '');

                if ('' != ($instmsg = $this->addon->getProperty('installmsg', ''))) {
                    throw new UserMessageException($instmsg);
                }
                if (!$this->addon->isInstalled()) {
                    throw new UserMessageException($this->i18n('no_reason'));
                }
            }

            // import install.sql
            $installSql = $this->addon->getPath(Addon::FILE_INSTALL_SQL);
            if ($installDump && is_readable($installSql)) {
                Util::importDump($installSql);
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

            $this->message = $this->i18n($reinstall ? 'reinstalled' : 'installed', $this->addon->getName());
            if ($successMessage) {
                $this->message .= ' ' . $successMessage;
            }

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        } catch (SqlException $e) {
            $this->message = 'SQL error: ' . $e->getMessage();
        }

        $this->addon->setProperty('install', false);
        $this->message = $this->i18n('no_install', $this->addon->getName()) . '<br />' . $this->message;

        return false;
    }

    /**
     * Uninstalls a addon.
     *
     * @param bool $installDump When TRUE, the sql dump will be importet
     * @return bool TRUE on success, FALSE on error
     */
    public function uninstall(bool $installDump = true): bool
    {
        $isActivated = $this->addon->isAvailable();
        if ($isActivated && !$this->deactivate()) {
            return false;
        }

        try {
            $this->addon->setProperty('install', false);

            // include uninstall.php
            if (is_readable($this->addon->getPath(Addon::FILE_UNINSTALL))) {
                if (!$isActivated) {
                    I18n::addDirectory($this->addon->getPath('lang'));
                }

                $this->addon->includeFile(Addon::FILE_UNINSTALL);

                if ('' != ($instmsg = $this->addon->getProperty('installmsg', ''))) {
                    throw new UserMessageException($instmsg);
                }
                if ($this->addon->isInstalled()) {
                    throw new UserMessageException($this->i18n('no_reason'));
                }
            }

            // import uninstall.sql
            $uninstallSql = $this->addon->getPath(Addon::FILE_UNINSTALL_SQL);
            if ($installDump && is_readable($uninstallSql)) {
                Util::importDump($uninstallSql);
            }

            // delete assets
            $assets = $this->addon->getAssetsPath();
            if (is_dir($assets) && !Dir::delete($assets)) {
                throw new UserMessageException($this->i18n('install_cant_delete_files'));
            }

            // clear cache of addon
            $this->addon->clearCache();

            Config::removeNamespace($this->addon->getPackageId());

            static::saveConfig();
            $this->message = $this->i18n('uninstalled', $this->addon->getName());

            return true;
        } catch (UserMessageException $e) {
            $this->message = $e->getMessage();
        } catch (SqlException $e) {
            $this->message = 'SQL error: ' . $e->getMessage();
        }

        $this->addon->setProperty('install', true);
        if ($isActivated) {
            $this->addon->setProperty('status', true);
        }
        static::saveConfig();
        $this->message = $this->i18n('no_uninstall', $this->addon->getName()) . '<br />' . $this->message;

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
            if (!$this->checkConflicts()) {
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
            $state = $this->i18n('not_installed', $this->addon->getName());
        }

        if (true !== $state) {
            // error while config generation, rollback addon status
            $this->addon->setProperty('status', false);
            $this->message = $this->i18n('no_activation', $this->addon->getName()) . '<br />' . $state;
            return false;
        }

        $this->message = $this->i18n('activated', $this->addon->getName());
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

            $this->message = $this->i18n('deactivated', $this->addon->getName());
            return true;
        }

        $this->message = $this->i18n('no_deactivation', $this->addon->getName()) . '<br />' . $this->message;
        return false;
    }

    /**
     * Deletes a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function delete(): bool
    {
        if ($this->addon->isSystemPackage()) {
            $this->message = $this->i18n('systempackage_delete_not_allowed');
            return false;
        }
        $state = $this->_delete();
        self::synchronizeWithFileSystem();
        return $state;
    }

    /**
     * Deletes a addon.
     *
     * @return bool TRUE on success, FALSE on error
     */
    protected function _delete(bool $ignoreState = false): bool
    {
        // if addon is installed, uninstall it first
        if ($this->addon->isInstalled() && !$this->uninstall() && !$ignoreState) {
            // message is set by uninstall()
            return false;
        }

        if (!Dir::delete($this->addon->getPath()) && !$ignoreState) {
            $this->message = $this->i18n('not_deleted', $this->addon->getName());
            return false;
        }

        if (!$ignoreState) {
            static::saveConfig();
            $this->message = $this->i18n('deleted', $this->addon->getName());
        }

        $this->addon->clearCache();

        return true;
    }

    protected function wrongPackageId(string $addonName): string
    {
        return $this->i18n('wrong_dir_name', $addonName);
    }

    /** Checks whether the requirements are met. */
    public function checkRequirements(): bool
    {
        $requirements = $this->addon->getProperty('requires', []);

        if (!is_array($requirements)) {
            $this->message = $this->i18n('requirement_wrong_format');

            return false;
        }

        if (!$this->checkRedaxoRequirement(Core::getVersion())) {
            return false;
        }

        $state = [];

        if (isset($requirements['php'])) {
            if (!is_array($requirements['php'])) {
                $requirements['php'] = ['version' => $requirements['php']];
            }
            if (isset($requirements['php']['version']) && !Version::matchesConstraints(PHP_VERSION, $requirements['php']['version'])) {
                $state[] = $this->i18n('requirement_error_php_version', PHP_VERSION, $requirements['php']['version']);
            }
            if (isset($requirements['php']['extensions']) && $requirements['php']['extensions']) {
                $extensions = (array) $requirements['php']['extensions'];
                foreach ($extensions as $reqExt) {
                    if (is_string($reqExt) && !extension_loaded($reqExt)) {
                        $state[] = $this->i18n('requirement_error_php_extension', $reqExt);
                    }
                }
            }
        }

        if (empty($state)) {
            if (isset($requirements['packages']) && is_array($requirements['packages'])) {
                foreach ($requirements['packages'] as $addon => $_) {
                    if (!$this->checkPackageRequirement($addon)) {
                        $state[] = $this->message;
                    }
                }
            }
        }

        if (empty($state)) {
            return true;
        }
        $this->message = implode('<br />', $state);
        return false;
    }

    /**
     * Checks whether the redaxo requirement is met.
     *
     * @param string $redaxoVersion REDAXO version
     */
    public function checkRedaxoRequirement(string $redaxoVersion): bool
    {
        $requirements = $this->addon->getProperty('requires', []);
        if (isset($requirements['redaxo']) && !Version::matchesConstraints($redaxoVersion, $requirements['redaxo'])) {
            $this->message = $this->i18n('requirement_error_redaxo_version', $redaxoVersion, $requirements['redaxo']);
            return false;
        }
        return true;
    }

    /** Checks whether the addon requirement is met. */
    public function checkPackageRequirement(string $addonId): bool
    {
        $requirements = $this->addon->getProperty('requires', []);
        if (!isset($requirements['packages'][$addonId])) {
            return true;
        }
        $addon = Addon::get($addonId);
        $requiredVersion = '';
        if (!$addon->isAvailable()) {
            if ('' != $requirements['packages'][$addonId]) {
                $requiredVersion = ' ' . $requirements['packages'][$addonId];
            }

            if (!Addon::exists($addonId)) {
                $jumpToInstaller = '';
                if (Addon::get('install')->isAvailable()) {
                    // addon need to be downloaded via installer
                    $installUrl = Url::backendPage('install/packages/add', ['addonkey' => $addonId]);

                    $jumpToInstaller = ' <a href="' . $installUrl . '"><i class="rex-icon fa-arrow-circle-right" title="' . $this->i18n('search_in_installer', $addonId) . '"></i></a>';
                }

                $this->message = $this->i18n('requirement_error_addon', $addonId . $requiredVersion) . $jumpToInstaller;
                return false;
            }

            $jumpPackageUrl = '#package-' . Str::normalize($addonId, '-', '_');
            if ('packages' !== Controller::getCurrentPage()) {
                // error while update/install within install-addon. x-link to packages core page
                $jumpPackageUrl = Url::backendPage('packages') . $jumpPackageUrl;
            }

            $this->message = $this->i18n('requirement_error_addon', $addonId . $requiredVersion) . ' <a href="' . $jumpPackageUrl . '"><i class="rex-icon fa-arrow-circle-right" title="' . $this->i18n('jump_to', $addonId) . '"></i></a>';
            return false;
        }

        if (!Version::matchesConstraints($addon->getVersion(), $requirements['packages'][$addonId])) {
            $this->message = $this->i18n(
                'requirement_error_addon_version',
                $addon->getPackageId(),
                $addon->getVersion(),
                $requirements['packages'][$addonId],
            );
            return false;
        }
        return true;
    }

    /** Checks whether the addon is in conflict with other packages. */
    public function checkConflicts(): bool
    {
        $state = [];
        $conflicts = $this->addon->getProperty('conflicts', []);

        if (isset($conflicts['packages']) && is_array($conflicts['packages'])) {
            foreach ($conflicts['packages'] as $addon => $_) {
                if (!$this->checkPackageConflict($addon)) {
                    $state[] = $this->message;
                }
            }
        }

        foreach (Addon::getAvailableAddons() as $addon) {
            $conflicts = $addon->getProperty('conflicts', []);

            if (!isset($conflicts['packages'][$this->addon->getPackageId()])) {
                continue;
            }

            $constraints = $conflicts['packages'][$this->addon->getPackageId()];
            if (!is_string($constraints) || !$constraints || '*' === $constraints) {
                $state[] = $this->i18n('reverse_conflict_error_addon', $addon->getPackageId());
            } elseif (Version::matchesConstraints($this->addon->getVersion(), $constraints)) {
                $state[] = $this->i18n('reverse_conflict_error_addon_version', $addon->getPackageId(), $constraints);
            }
        }

        if (empty($state)) {
            return true;
        }
        $this->message = implode('<br />', $state);
        return false;
    }

    /** Checks whether the addon is in conflict with another package. */
    public function checkPackageConflict(string $addonId): bool
    {
        $conflicts = $this->addon->getProperty('conflicts', []);
        $addon = Addon::get($addonId);
        if (!isset($conflicts['packages'][$addonId]) || !$addon->isAvailable()) {
            return true;
        }
        $constraints = $conflicts['packages'][$addonId];
        if (!is_string($constraints) || !$constraints || '*' === $constraints) {
            $this->message = $this->i18n('conflict_error_addon', $addon->getPackageId());
            return false;
        }
        if (Version::matchesConstraints($addon->getVersion(), $constraints)) {
            $this->message = $this->i18n('conflict_error_addon_version', $addon->getPackageId(), $constraints);
            return false;
        }
        return true;
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

            $requirements = $addon->getProperty('requires', []);
            if (isset($requirements['packages'][$this->addon->getPackageId()])) {
                $state[] = I18n::msg($i18nPrefix . 'addon', $addon->getPackageId());
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
            $id = $addon->getPackageId();
            $load = $addon->getProperty('load');
            if ('early' === $load) {
                $early[] = $id;
            } elseif ('late' === $load) {
                $late[] = $id;
            } else {
                $req = $addon->getProperty('requires');
                if (isset($req['packages']) && is_array($req['packages'])) {
                    foreach ($req['packages'] as $addonId => $reqP) {
                        $addon = Addon::get($addonId);
                        if (!in_array($addonId, $normal) && !in_array($addon->getProperty('load'), ['early', 'late'])) {
                            $requires[$id][$addonId] = true;
                        }
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
            $config[$addonName]['install'] = $addon->isInstalled();
            $config[$addonName]['status'] = $addon->isAvailable();
        }
        Core::setConfig('package-config', $config);
    }

    /** Synchronizes the addons with the file system. */
    public static function synchronizeWithFileSystem(): void
    {
        $config = Core::getPackageConfig();
        $addons = self::readPackageFolder(Path::src('addons'));
        $registeredAddons = array_keys(Addon::getRegisteredAddons());
        foreach (array_diff($registeredAddons, $addons) as $addonName) {
            $manager = self::factory(Addon::require($addonName));
            $manager->_delete(true);
            unset($config[$addonName]);
        }
        foreach ($addons as $addonName) {
            if (!Addon::exists($addonName)) {
                $config[$addonName]['install'] = false;
                $config[$addonName]['status'] = false;
            } else {
                $addon = Addon::get($addonName);
                $config[$addonName]['install'] = $addon->isInstalled();
                $config[$addonName]['status'] = $addon->isAvailable();
            }
        }
        ksort($config);

        Core::setConfig('package-config', $config);
        Addon::initialize();
    }

    /**
     * Returns the subfolders of the given folder.
     *
     * @return list<non-empty-string>
     */
    private static function readPackageFolder(string $folder): array
    {
        $addons = [];

        if (is_dir($folder)) {
            foreach (Finder::factory($folder)->dirsOnly() as $file) {
                $addons[] = $file->getBasename();
            }
        }

        return $addons;
    }
}
