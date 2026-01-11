<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonInterface;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Version;

/**
 * @internal
 */
class rex_api_install_core_update extends ApiFunction
{
    /**
     * @return array<int, array{version: string, description: string, path: string, checksum: string}>
     */
    public static function getVersions()
    {
        return rex_install_webservice::getJson('core');
    }

    public function execute()
    {
        if (Core::isLiveMode()) {
            throw new ApiFunctionException('Core update is not available in live mode!');
        }
        if (!Core::getUser()?->isAdmin()) {
            throw new ApiFunctionException('You do not have the permission!');
        }
        $installAddon = Addon::get('install');
        $versions = self::getVersions();
        $versionId = Request::request('version_id', 'int');

        if (!isset($versions[$versionId])) {
            throw new ApiFunctionException('The requested core version can not be loaded, maybe it is already installed.');
        }
        $version = $versions[$versionId];
        if (!Version::compare($version['version'], Core::getVersion(), '>')) {
            throw new ApiFunctionException(sprintf('Existing version of Core (%s) is newer than %s', Core::getVersion(), $version['version']));
        }
        if (!is_writable(Path::core())) {
            throw new UserMessageException($installAddon->i18n('warning_directory_not_writable', Path::core()));
        }
        try {
            $archivefile = rex_install_webservice::getArchive($version['path']);
        } catch (UserMessageException $e) {
            throw new ApiFunctionException($e->getMessage());
        }

        // load logger class before core update to avoid getting logger class from new core while logging success message
        $logger = Logger::factory();

        $message = '';
        $temppath = Path::coreCache('.new.core/');
        $coreAddons = [];
        $updateAddons = [];
        $updateAddonsConfig = [];
        try {
            if ($version['checksum'] != md5_file($archivefile)) {
                throw new UserMessageException($installAddon->i18n('warning_zip_wrong_checksum'));
            }

            // remove temp dir very late otherwise Whoops could not find source files in case of errors
            register_shutdown_function(static fn () => Dir::delete($temppath));

            if (!rex_install_archive::extract($archivefile, $temppath)) {
                throw new UserMessageException($installAddon->i18n('warning_core_zip_not_extracted'));
            }
            if (!is_dir($temppath . 'core')) {
                throw new UserMessageException($installAddon->i18n('warning_zip_wrong_format'));
            }
            if (is_dir($temppath . 'addons')) {
                foreach (Finder::factory($temppath . 'addons')->dirsOnly() as $dir) {
                    $addonkey = $dir->getBasename();
                    $addonPath = $dir->getRealPath() . '/';
                    if (!is_file($addonPath . Addon::FILE_PACKAGE)) {
                        continue;
                    }

                    $config = File::getConfig($addonPath . Addon::FILE_PACKAGE);
                    if (
                        '' == $addonkey
                        || !isset($config['version'])
                        || Addon::exists($addonkey) && Version::compare($config['version'], Addon::get($addonkey)->getVersion(), '<')
                    ) {
                        continue;
                    }

                    $coreAddons[$addonkey] = $addonkey;
                    if (Addon::exists($addonkey)) {
                        $addon = Addon::get($addonkey);

                        if (!is_writable($addon->getPath())) {
                            throw new UserMessageException($installAddon->i18n('warning_directory_not_writable', $addon->getPath()));
                        }

                        $updateAddons[$addonkey] = $addon;
                        $updateAddonsConfig[$addonkey] = $config;
                    }
                }
            }
            // $config = File::getConfig($temppath . 'core/default.config.yml');
            // foreach ($config['system_addons'] as $addonkey) {
            //    if (is_dir($temppath . 'addons/' . $addonkey) && Addon::exists($addonkey)) {
            //        $updateAddons[$addonkey] = Addon::get($addonkey);
            //    }
            // }
            $this->checkRequirements($temppath, $version['version'], $updateAddonsConfig);
            if (is_file($temppath . 'core/setup/update.php')) {
                include $temppath . 'core/setup/update.php';
            }
            foreach ($updateAddons as $addonkey => $addon) {
                if ($addon->isInstalled() && is_file($file = $temppath . 'addons/' . $addonkey . '/' . Addon::FILE_UPDATE)) {
                    try {
                        $addon->includeFile($file);
                        if ($msg = $addon->getProperty('updatemsg', '')) {
                            throw new UserMessageException($msg);
                        }
                        if (!$addon->getProperty('update', true)) {
                            throw new UserMessageException(I18n::msg('package_no_reason'));
                        }
                    } catch (UserMessageException $e) {
                        throw new UserMessageException($addonkey . ': ' . $e->getMessage(), $e);
                    } catch (SqlException $e) {
                        throw new UserMessageException($addonkey . ': SQL error: ' . $e->getMessage(), $e);
                    }
                }

                if ($addon->isInstalled()) {
                    foreach ($addon->getProperty('default_config', []) as $key => $value) {
                        if (!$addon->hasConfig($key)) {
                            $addon->setConfig($key, $value);
                        }
                    }
                }
            }

            // create backup
            $installConfig = File::getCache($installAddon->getDataPath('config.json'));
            $pathCore = Path::core();
            if (isset($installConfig['backups']) && $installConfig['backups']) {
                Dir::create($installAddon->getDataPath());
                $archive = $installAddon->getDataPath(strtolower(preg_replace('/[^a-z0-9-_.]/i', '_', Core::getVersion())) . '.zip');
                rex_install_archive::copyDirToArchive($pathCore, $archive);
                foreach ($updateAddons as $addonkey => $addon) {
                    rex_install_archive::copyDirToArchive($addon->getPath(), $archive, 'addons/' . $addonkey);
                }
            }

            // move temp dirs to permanent destination
            error_clear_last();
            $pathOld = Path::src('core.old');
            // move current core to temp path
            if (!@rename($pathCore, $pathOld)) {
                $message = $pathCore . ' could not be moved to ' . $pathOld;
                $message .= ($error = error_get_last()) ? ': ' . $error['message'] : '.';
                throw new UserMessageException($message);
            }
            // move new core to main core path
            if (@rename($temppath . 'core', $pathCore)) {
                // remove temp path of old core
                Dir::delete($pathOld);
            } else {
                // revert to old core
                rename($pathOld, $pathCore);

                $message = $temppath . 'core could not be moved to ' . $pathCore;
                $message .= ($error = error_get_last()) ? ': ' . $error['message'] : '.';
                throw new UserMessageException($message);
            }

            if (is_dir(Path::core('assets'))) {
                Dir::copy(Path::core('assets'), Path::coreAssets());
            }
            foreach ($coreAddons as $addonkey) {
                if (isset($updateAddons[$addonkey])) {
                    $pathOld = Path::addon($addonkey . '.old');
                    // move whole old addon to a temp dir (high priority to get the free space for new addon version)
                    // and try to delete it afterwards (lower priority)
                    rename(Path::addon($addonkey), $pathOld);
                    Dir::delete($pathOld);
                }
                rename($temppath . 'addons/' . $addonkey, Path::addon($addonkey));
                if (is_dir(Path::addon($addonkey, 'assets'))) {
                    Dir::copy(Path::addon($addonkey, 'assets'), Path::addonAssets($addonkey));
                }
            }
        } catch (UserMessageException $e) {
            $message = $e->getMessage();
        } catch (SqlException $e) {
            $message = 'SQL error: ' . $e->getMessage();
        } finally {
            File::delete($archivefile);
        }

        if ($message) {
            $message = $installAddon->i18n('warning_core_not_updated') . '<br />' . $message;
            $success = false;
        } else {
            $logger->info('REDAXO Core updated from ' . Core::getVersion() . ' to version ' . $version['version']);

            $message = $installAddon->i18n('info_core_updated');
            $success = true;
            Cache::delete();
            rex_install_webservice::deleteCache();
            rex_install_packages::deleteCache();
            Core::setConfig('version', $version['version']);

            // ---- update package order
            /** @var Addon $addon */
            foreach ($updateAddons as $addon) {
                if ($addon->isAvailable()) {
                    $addon->loadProperties(true);
                }
            }
            AddonManager::generatePackageOrder();

            // re-generate opcache to make sure new/updated classes immediately are available
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
        }

        $result = new Result($success, $message);
        if ($success) {
            $result->setRequiresReboot(true);
        }

        return $result;
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }

    /**
     * @param string $temppath
     * @param string $version
     * @throws UserMessageException
     * @return void
     */
    private function checkRequirements($temppath, $version, array $addons)
    {
        // ---- update "version", "requires" and "conflicts" properties
        $coreVersion = Core::getVersion();
        Core::setProperty('version', $version);

        /** @var SplObjectStorage<AddonInterface, string> $versions */
        $versions = new SplObjectStorage();
        /** @var SplObjectStorage<AddonInterface, array> $requirements */
        $requirements = new SplObjectStorage();
        /** @var SplObjectStorage<AddonInterface, array> $conflicts */
        $conflicts = new SplObjectStorage();

        foreach ($addons as $addonkey => $config) {
            $addon = Addon::get($addonkey);
            $addonPath = $temppath . 'addons/' . $addonkey . '/';

            $requirements[$addon] = $addon->getProperty('requires', []);
            $addon->setProperty('requires', $config['requires'] ?? []);

            $conflicts[$addon] = $addon->getProperty('conflicts', []);
            $addon->setProperty('conflicts', $config['conflicts'] ?? []);

            $versions[$addon] = $addon->getVersion();
            $addon->setProperty('version', $config['version']);
        }

        // ---- check requirements
        $messages = [];
        foreach (Addon::getAvailableAddons() as $package) {
            $manager = AddonManager::factory($package);
            if (!$manager->checkRequirements()) {
                $messages[] = $this->messageFromPackage($package, $manager);
            } elseif (!$manager->checkConflicts()) {
                $messages[] = $this->messageFromPackage($package, $manager);
            }
        }

        // ---- reset "version", "requires" and "conflicts" properties
        Core::setProperty('version', $coreVersion);
        foreach ($versions as $package) {
            $package->setProperty('version', $versions[$package]);
        }
        foreach ($requirements as $package) {
            $package->setProperty('requires', $requirements[$package]);
        }
        foreach ($conflicts as $package) {
            $package->setProperty('conflicts', $conflicts[$package]);
        }

        if (!empty($messages)) {
            throw new UserMessageException('<ul><li>' . implode('</li><li>', $messages) . '</li></ul>');
        }
    }

    private function messageFromPackage(Addon $package, AddonManager $manager): string
    {
        return I18n::msg('install_warning_message_from_addon', $package->getPackageId()) . ' ' . $manager->getMessage();
    }
}
