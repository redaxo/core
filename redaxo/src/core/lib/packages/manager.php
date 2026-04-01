<?php

/**
 * Manager class for packages.
 *
 * @template T as rex_package
 *
 * @package redaxo\core\packages
 */
abstract class rex_package_manager
{
    use rex_factory_trait;

    /** @var T */
    protected $package;

    /** @var bool */
    protected $generatePackageOrder = true;

    /** @var string */
    protected $message;

    /** @var string */
    private $i18nPrefix;

    /**
     * @param T $package Package
     * @param string $i18nPrefix Prefix for i18n
     */
    protected function __construct(rex_package $package, $i18nPrefix)
    {
        $this->package = $package;
        $this->i18nPrefix = $i18nPrefix;
    }

    /**
     * Creates the manager for the package.
     *
     * @template TS as rex_package
     * @param TS $package Package
     * @return (TS is rex_plugin ? rex_plugin_manager : (TS is rex_addon ? rex_addon_manager : self))
     */
    public static function factory(rex_package $package)
    {
        if (self::class === static::class) {
            $class = $package instanceof rex_plugin ? rex_plugin_manager::class : rex_addon_manager::class;
            return $class::factory($package);
        }
        $class = static::getFactoryClass();
        return new $class($package);
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Installs a package.
     *
     * @param bool $installDump When TRUE, the sql dump will be importet
     *
     * @throws rex_functional_exception
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function install($installDump = true)
    {
        try {
            // check package directory perms
            $installDir = $this->package->getPath();
            if (!rex_dir::isWritable($installDir)) {
                throw new rex_functional_exception($this->i18n('dir_not_writable', $installDir));
            }

            // check package.yml
            $packageFile = $this->package->getPath(rex_package::FILE_PACKAGE);
            if (!is_readable($packageFile)) {
                throw new rex_functional_exception($this->i18n('missing_yml_file'));
            }
            try {
                rex_file::getConfig($packageFile);
            } catch (rex_yaml_parse_exception $e) {
                throw new rex_functional_exception($this->i18n('invalid_yml_file') . ' ' . $e->getMessage());
            }
            $packageId = $this->package->getProperty('package');
            if (null === $packageId) {
                throw new rex_functional_exception($this->i18n('missing_id', $this->package->getPackageId()));
            }
            if ($packageId != $this->package->getPackageId()) {
                [$addonId, $pluginId] = rex_package::splitId($packageId);
                throw new rex_functional_exception($this->wrongPackageId($addonId, $pluginId));
            }
            if (null === $this->package->getProperty('version')) {
                throw new rex_functional_exception($this->i18n('missing_version'));
            }

            if ($this->package instanceof rex_plugin_interface && !$this->package->getAddon()->isAvailable()) {
                throw new rex_functional_exception($this->i18n('requirement_error_addon', $this->package->getAddon()->getName()));
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
                throw new rex_functional_exception($message);
            }

            $reinstall = $this->package->getProperty('install');
            $this->package->setProperty('install', true);

            rex_autoload::addDirectory($this->package->getPath('lib'));
            rex_autoload::addDirectory($this->package->getPath('vendor'));
            rex_i18n::addDirectory($this->package->getPath('lang'));

            // include install.php
            $successMessage = '';
            if (is_readable($this->package->getPath(rex_package::FILE_INSTALL))) {
                $this->package->includeFile(rex_package::FILE_INSTALL);
                $successMessage = $this->package->getProperty('successmsg', '');

                /** @psalm-taint-escape html */
                $instmsg = (string) $this->package->getProperty('installmsg', '');
                if ('' != $instmsg) {
                    throw new rex_functional_exception($instmsg);
                }
                if (!$this->package->isInstalled()) {
                    throw new rex_functional_exception($this->i18n('no_reason'));
                }
            }

            // import install.sql
            $installSql = $this->package->getPath(rex_package::FILE_INSTALL_SQL);
            if ($installDump && is_readable($installSql)) {
                rex_sql_util::importDump($installSql);
            }

            if (!$reinstall) {
                $this->package->setProperty('status', true);
            }
            static::saveConfig();
            if ($this->generatePackageOrder) {
                self::generatePackageOrder();
            }

            foreach ($this->package->getProperty('default_config', []) as $key => $value) {
                if (!$this->package->hasConfig($key)) {
                    $this->package->setConfig($key, $value);
                }
            }

            // copy assets
            $assets = $this->package->getPath('assets');
            if (is_dir($assets)) {
                if (!rex_dir::copy($assets, $this->package->getAssetsPath())) {
                    throw new rex_functional_exception($this->i18n('install_cant_copy_files'));
                }
            }

            $this->message = $this->i18n($reinstall ? 'reinstalled' : 'installed', $this->package->getName());
            if ($successMessage) {
                $this->message .= ' ' . $successMessage;
            }

            return true;
        } catch (rex_functional_exception $e) {
            $this->message = $e->getMessage();
        } catch (rex_sql_exception $e) {
            $this->message = 'SQL error: ' . $e->getMessage();
        }

        $this->package->setProperty('install', false);
        $this->message = $this->i18n('no_install', $this->package->getName()) . '<br />' . $this->message;

        return false;
    }

    /**
     * Uninstalls a package.
     *
     * @param bool $installDump When TRUE, the sql dump will be importet
     *
     * @throws rex_functional_exception
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function uninstall($installDump = true)
    {
        $isActivated = $this->package->isAvailable();
        if ($isActivated && !$this->deactivate()) {
            return false;
        }

        try {
            $this->package->setProperty('install', false);

            // include uninstall.php
            if (is_readable($this->package->getPath(rex_package::FILE_UNINSTALL))) {
                if (!$isActivated) {
                    rex_i18n::addDirectory($this->package->getPath('lang'));
                }

                $this->package->includeFile(rex_package::FILE_UNINSTALL);

                /** @psalm-taint-escape html */
                $instmsg = (string) $this->package->getProperty('installmsg', '');
                if ('' != $instmsg) {
                    throw new rex_functional_exception($instmsg);
                }
                if ($this->package->isInstalled()) {
                    throw new rex_functional_exception($this->i18n('no_reason'));
                }
            }

            // import uninstall.sql
            $uninstallSql = $this->package->getPath(rex_package::FILE_UNINSTALL_SQL);
            if ($installDump && is_readable($uninstallSql)) {
                rex_sql_util::importDump($uninstallSql);
            }

            // delete assets
            $assets = $this->package->getAssetsPath();
            if (is_dir($assets) && !rex_dir::delete($assets)) {
                throw new rex_functional_exception($this->i18n('install_cant_delete_files'));
            }

            // clear cache of package
            $this->package->clearCache();

            rex_config::removeNamespace($this->package->getPackageId());

            static::saveConfig();
            $this->message = $this->i18n('uninstalled', $this->package->getName());

            return true;
        } catch (rex_functional_exception $e) {
            $this->message = $e->getMessage();
        } catch (rex_sql_exception $e) {
            $this->message = 'SQL error: ' . $e->getMessage();
        }

        $this->package->setProperty('install', true);
        if ($isActivated) {
            $this->package->setProperty('status', true);
        }
        static::saveConfig();
        $this->message = $this->i18n('no_uninstall', $this->package->getName()) . '<br />' . $this->message;

        return false;
    }

    /**
     * Activates a package.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function activate()
    {
        if ($this->package->isInstalled()) {
            $state = '';
            if (!$this->checkRequirements()) {
                $state .= $this->message;
            }
            if (!$this->checkConflicts()) {
                $state .= $this->message;
            }
            $state = $state ?: true;

            if (true === $state) {
                $this->package->setProperty('status', true);
                static::saveConfig();
            }
            if (true === $state && $this->generatePackageOrder) {
                self::generatePackageOrder();
            }
        } else {
            $state = $this->i18n('not_installed', $this->package->getName());
        }

        if (true !== $state) {
            // error while config generation, rollback addon status
            $this->package->setProperty('status', false);
            $this->message = $this->i18n('no_activation', $this->package->getName()) . '<br />' . $state;
            return false;
        }

        $this->message = $this->i18n('activated', $this->package->getName());
        return true;
    }

    /**
     * Deactivates a package.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function deactivate()
    {
        $state = $this->checkDependencies();

        if ($state) {
            $this->package->setProperty('status', false);
            static::saveConfig();

            // clear cache of package
            $this->package->clearCache();

            // reload autoload cache when addon is deactivated,
            // so the index doesn't contain outdated class definitions
            rex_autoload::removeCache();

            if ($this->generatePackageOrder) {
                self::generatePackageOrder();
            }

            $this->message = $this->i18n('deactivated', $this->package->getName());
            return true;
        }

        $this->message = $this->i18n('no_deactivation', $this->package->getName()) . '<br />' . $this->message;
        return false;
    }

    /**
     * Deletes a package.
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function delete()
    {
        if ($this->package->isSystemPackage()) {
            $this->message = $this->i18n('systempackage_delete_not_allowed');
            return false;
        }
        $state = $this->_delete();
        self::synchronizeWithFileSystem();
        return $state;
    }

    /**
     * Deletes a package.
     *
     * @param bool $ignoreState
     *
     * @return bool TRUE on success, FALSE on error
     */
    protected function _delete($ignoreState = false)
    {
        // if package is installed, uninstall it first
        if ($this->package->isInstalled() && !$this->uninstall() && !$ignoreState) {
            // message is set by uninstall()
            return false;
        }

        if (!rex_dir::delete($this->package->getPath()) && !$ignoreState) {
            $this->message = $this->i18n('not_deleted', $this->package->getName());
            return false;
        }

        if (!$ignoreState) {
            static::saveConfig();
            $this->message = $this->i18n('deleted', $this->package->getName());
        }

        $this->package->clearCache();

        return true;
    }

    /**
     * @param string $addonName
     * @param string $pluginName
     *
     * @return string
     */
    abstract protected function wrongPackageId($addonName, $pluginName = null);

    /**
     * Checks whether the requirements are met.
     *
     * @return bool
     */
    public function checkRequirements()
    {
        $requirements = $this->package->getProperty('requires', []);

        if (!is_array($requirements)) {
            $this->message = $this->i18n('requirement_wrong_format');

            return false;
        }

        if (!$this->checkRedaxoRequirement(rex::getVersion())) {
            return false;
        }

        $state = [];

        if (isset($requirements['php'])) {
            if (!is_array($requirements['php'])) {
                $requirements['php'] = ['version' => $requirements['php']];
            }
            if (isset($requirements['php']['version']) && !rex_version::matchesConstraints(PHP_VERSION, $requirements['php']['version'])) {
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
                foreach ($requirements['packages'] as $package => $_) {
                    if (!$this->checkPackageRequirement($package)) {
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
     *
     * @return bool
     */
    public function checkRedaxoRequirement($redaxoVersion)
    {
        $requirements = $this->package->getProperty('requires', []);
        if (isset($requirements['redaxo']) && !rex_version::matchesConstraints($redaxoVersion, $requirements['redaxo'])) {
            $this->message = $this->i18n('requirement_error_redaxo_version', $redaxoVersion, $requirements['redaxo']);
            return false;
        }
        return true;
    }

    /**
     * Checks whether the package requirement is met.
     *
     * @param string $packageId Package ID
     *
     * @return bool
     */
    public function checkPackageRequirement($packageId)
    {
        $requirements = $this->package->getProperty('requires', []);
        if (!isset($requirements['packages'][$packageId])) {
            return true;
        }
        $package = rex_package::get($packageId);
        $requiredVersion = '';
        if (!$package->isAvailable()) {
            if ('' != $requirements['packages'][$packageId]) {
                $requiredVersion = ' ' . $requirements['packages'][$packageId];
            }

            if (!rex_package::exists($packageId)) {
                [$addonId] = rex_package::splitId($packageId);
                $jumpToInstaller = '';
                if (rex_addon::get('install')->isAvailable() && !rex_addon::exists($addonId)) {
                    // package need to be downloaded via installer
                    $installUrl = rex_url::backendPage('install/packages/add', ['addonkey' => $addonId]);

                    $jumpToInstaller = ' <a href="' . $installUrl . '"><i class="rex-icon fa-arrow-circle-right" title="' . $this->i18n('search_in_installer', $addonId) . '"></i></a>';
                }

                $this->message = $this->i18n('requirement_error_' . $package->getType(), $packageId . $requiredVersion) . $jumpToInstaller;
                return false;
            }

            // this package requires a plugin from another addon.
            // first make sure the addon itself is available.
            $jumpPackageId = $packageId;
            if ($package instanceof rex_plugin_interface && !$package->getAddon()->isAvailable()) {
                $jumpPackageId = (string) $package->getAddon()->getPackageId();
            }

            $jumpPackageUrl = '#package-' . rex_string::normalize($jumpPackageId, '-', '_');
            if ('packages' !== rex_be_controller::getCurrentPage()) {
                // error while update/install within install-addon. x-link to packages core page
                $jumpPackageUrl = rex_url::backendPage('packages') . $jumpPackageUrl;
            }

            $this->message = $this->i18n('requirement_error_' . $package->getType(), $packageId . $requiredVersion) . ' <a href="' . $jumpPackageUrl . '"><i class="rex-icon fa-arrow-circle-right" title="' . $this->i18n('jump_to', $jumpPackageId) . '"></i></a>';
            return false;
        }

        if (!rex_version::matchesConstraints($package->getVersion(), $requirements['packages'][$packageId])) {
            $this->message = $this->i18n(
                'requirement_error_' . $package->getType() . '_version',
                $package->getPackageId(),
                $package->getVersion(),
                $requirements['packages'][$packageId],
            );
            return false;
        }
        return true;
    }

    /**
     * Checks whether the package is in conflict with other packages.
     *
     * @return bool
     */
    public function checkConflicts()
    {
        $state = [];
        $conflicts = $this->package->getProperty('conflicts', []);

        if (isset($conflicts['packages']) && is_array($conflicts['packages'])) {
            foreach ($conflicts['packages'] as $package => $_) {
                if (!$this->checkPackageConflict($package)) {
                    $state[] = $this->message;
                }
            }
        }

        foreach (rex_package::getAvailablePackages() as $package) {
            $conflicts = $package->getProperty('conflicts', []);

            if (!isset($conflicts['packages'][$this->package->getPackageId()])) {
                continue;
            }

            $constraints = $conflicts['packages'][$this->package->getPackageId()];
            if (!is_string($constraints) || !$constraints || '*' === $constraints) {
                $state[] = $this->i18n('reverse_conflict_error_' . $package->getType(), $package->getPackageId());
            } elseif (rex_version::matchesConstraints($this->package->getVersion(), $constraints)) {
                $state[] = $this->i18n('reverse_conflict_error_' . $package->getType() . '_version', $package->getPackageId(), $constraints);
            }
        }

        if (empty($state)) {
            return true;
        }
        $this->message = implode('<br />', $state);
        return false;
    }

    /**
     * Checks whether the package is in conflict with another package.
     *
     * @param string $packageId Package ID
     *
     * @return bool
     */
    public function checkPackageConflict($packageId)
    {
        $conflicts = $this->package->getProperty('conflicts', []);
        $package = rex_package::get($packageId);
        if (!isset($conflicts['packages'][$packageId]) || !$package->isAvailable()) {
            return true;
        }
        $constraints = $conflicts['packages'][$packageId];
        if (!is_string($constraints) || !$constraints || '*' === $constraints) {
            $this->message = $this->i18n('conflict_error_' . $package->getType(), $package->getPackageId());
            return false;
        }
        if (rex_version::matchesConstraints($package->getVersion(), $constraints)) {
            $this->message = $this->i18n('conflict_error_' . $package->getType() . '_version', $package->getPackageId(), $constraints);
            return false;
        }
        return true;
    }

    /**
     * Checks if another Package which is activated, depends on the given package.
     *
     * @return bool
     */
    public function checkDependencies()
    {
        $i18nPrefix = 'package_dependencies_error_';
        $state = [];

        foreach (rex_package::getAvailablePackages() as $package) {
            if ($package === $this->package || $package->getAddon() === $this->package) {
                continue;
            }

            $requirements = $package->getProperty('requires', []);
            if (isset($requirements['packages'][$this->package->getPackageId()])) {
                $state[] = rex_i18n::msg($i18nPrefix . $package->getType(), $package->getPackageId());
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
    protected function i18n($key)
    {
        $args = func_get_args();
        $key = $this->i18nPrefix . $args[0];
        if (!rex_i18n::hasMsg($key)) {
            $key = 'package_' . $args[0];
        }
        $args[0] = $key;

        return call_user_func_array(rex_i18n::msg(...), $args);
    }

    /**
     * Generates the package order.
     * @return void
     */
    public static function generatePackageOrder()
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
        foreach (rex_package::getAvailablePackages() as $package) {
            $id = $package->getPackageId();
            $load = $package->getProperty('load');
            if ($package instanceof rex_plugin
                && !in_array($load, ['early', 'normal', 'late'])
                && in_array($addonLoad = $package->getAddon()->getProperty('load'), ['early', 'late'])
            ) {
                $load = $addonLoad;
            }
            if ('early' === $load) {
                $early[] = $id;
            } elseif ('late' === $load) {
                $late[] = $id;
            } else {
                $req = $package->getProperty('requires');
                if ($package instanceof rex_plugin) {
                    $req['packages'][$package->getAddon()->getPackageId()] = true;
                }
                if (isset($req['packages']) && is_array($req['packages'])) {
                    foreach ($req['packages'] as $packageId => $reqP) {
                        $package = rex_package::get($packageId);
                        if (!in_array($packageId, $normal) && !in_array($package->getProperty('load'), ['early', 'late'])) {
                            $requires[$id][$packageId] = true;
                        }
                    }
                }
                if (!isset($requires[$id])) {
                    $add($id);
                }
            }
        }
        rex::setConfig('package-order', array_merge($early, $normal, array_keys($requires), $late));
    }

    /**
     * Saves the package config.
     * @return void
     */
    protected static function saveConfig()
    {
        $config = [];
        foreach (rex_addon::getRegisteredAddons() as $addonName => $addon) {
            $config[$addonName]['install'] = $addon->isInstalled();
            $config[$addonName]['status'] = $addon->isAvailable();
            foreach ($addon->getRegisteredPlugins() as $pluginName => $plugin) {
                $config[$addonName]['plugins'][$pluginName]['install'] = $plugin->isInstalled();
                $config[$addonName]['plugins'][$pluginName]['status'] = $plugin->getProperty('status');
            }
        }
        rex::setConfig('package-config', $config);
    }

    /**
     * Synchronizes the packages with the file system.
     * @return void
     */
    public static function synchronizeWithFileSystem()
    {
        $config = rex::getPackageConfig();
        $addons = self::readPackageFolder(rex_path::src('addons'));
        $registeredAddons = array_keys(rex_addon::getRegisteredAddons());
        foreach (array_diff($registeredAddons, $addons) as $addonName) {
            $manager = rex_addon_manager::factory(rex_addon::require($addonName));
            $manager->_delete(true);
            unset($config[$addonName]);
        }
        foreach ($addons as $addonName) {
            if (!rex_addon::exists($addonName)) {
                $config[$addonName]['install'] = false;
                $config[$addonName]['status'] = false;
                $registeredPlugins = [];
            } else {
                $addon = rex_addon::get($addonName);
                $config[$addonName]['install'] = $addon->isInstalled();
                $config[$addonName]['status'] = $addon->isAvailable();
                $registeredPlugins = array_keys($addon->getRegisteredPlugins());
            }
            $plugins = self::readPackageFolder(rex_path::addon($addonName, 'plugins'));
            foreach (array_diff($registeredPlugins, $plugins) as $pluginName) {
                $manager = rex_plugin_manager::factory(rex_plugin::require($addonName, $pluginName));
                $manager->_delete(true);
                unset($config[$addonName]['plugins'][$pluginName]);
            }
            foreach ($plugins as $pluginName) {
                $plugin = rex_plugin::get($addonName, $pluginName);
                $config[$addonName]['plugins'][$pluginName]['install'] = $plugin->isInstalled();
                $config[$addonName]['plugins'][$pluginName]['status'] = $plugin->getProperty('status');
            }
            if (isset($config[$addonName]['plugins']) && is_array($config[$addonName]['plugins'])) {
                ksort($config[$addonName]['plugins']);
            }
        }
        ksort($config);

        rex::setConfig('package-config', $config);
        rex_addon::initialize();
    }

    /**
     * Returns the subfolders of the given folder.
     *
     * @param string $folder Folder
     *
     * @return list<non-empty-string>
     */
    private static function readPackageFolder($folder)
    {
        $packages = [];

        if (is_dir($folder)) {
            foreach (rex_finder::factory($folder)->dirsOnly() as $file) {
                $packages[] = $file->getBasename();
            }
        }

        return $packages;
    }
}
