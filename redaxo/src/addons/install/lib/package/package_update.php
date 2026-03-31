<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Exception\YamlParseException;
use Redaxo\Core\Util\Version;

/**
 * @internal
 *
 * @psalm-suppress MissingConstructor
 */
class rex_install_package_update extends rex_install_package_download
{
    /** @var Addon */
    private $addon;

    protected function getPackages()
    {
        return rex_install_packages::getUpdatePackages();
    }

    protected function checkPreConditions()
    {
        if (!Addon::exists($this->addonkey)) {
            throw new UserMessageException(sprintf('AddOn "%s" does not exist!', $this->addonkey));
        }
        $addon = Addon::get($this->addonkey);
        assert($addon instanceof Addon);
        $this->addon = $addon;
        if (!Version::compare($this->file['version'], $this->addon->getVersion(), '>')) {
            throw new UserMessageException(sprintf('Existing version of AddOn "%s" (%s) is newer than %s', $this->addonkey, $this->addon->getVersion(), $this->file['version']));
        }
    }

    public function doAction()
    {
        $path = Path::addon($this->addonkey);
        $temppath = Path::addon('.new.' . $this->addonkey);
        $oldVersion = $this->addon->getVersion();

        // remove temp dir very late otherwise Whoops could not find source files in case of errors
        register_shutdown_function(static fn () => Dir::delete($temppath));

        if (true !== ($msg = $this->extractArchiveTo($temppath))) {
            return $msg;
        }

        // ---- check package.yml
        $packageFile = $temppath . Addon::FILE_PACKAGE;
        if (!is_file($packageFile)) {
            return I18n::msg('package_missing_yml_file');
        }
        try {
            $config = File::getConfig($packageFile);
        } catch (YamlParseException $e) {
            return I18n::msg('package_invalid_yml_file') . ' ' . $e->getMessage();
        }

        if ($this->addon->isAvailable() && true !== ($msg = $this->checkRequirements($config))) {
            return $msg;
        }

        // ---- include update.php
        if ($this->addon->isInstalled() && is_file($temppath . Addon::FILE_UPDATE)) {
            try {
                $this->addon->includeFile('../.new.' . $this->addonkey . '/' . Addon::FILE_UPDATE);
            } catch (UserMessageException $e) {
                return $e->getMessage();
            } catch (SqlException $e) {
                return 'SQL error: ' . $e->getMessage();
            }
            if ('' != ($msg = (string) $this->addon->getProperty('updatemsg', ''))) {
                return $msg;
            }
            if (!$this->addon->getProperty('update', true)) {
                return I18n::msg('package_no_reason');
            }
        }

        if ($this->addon->isInstalled() && isset($config['default_config'])) {
            foreach ($config['default_config'] as $key => $value) {
                if (!$this->addon->hasConfig($key)) {
                    $this->addon->setConfig($key, $value);
                }
            }
        }

        // ---- backup
        $assets = $this->addon->getAssetsPath();
        $installConfig = File::getCache(Addon::get('install')->getDataPath('config.json'));
        if (isset($installConfig['backups']) && $installConfig['backups']) {
            $archivePath = Path::addonData('install', $this->addonkey . '/');
            Dir::create($archivePath);
            $archive = $archivePath . strtolower(preg_replace('/[^a-z0-9-_.]/i', '_', $this->addon->getVersion() ?: '0')) . '.zip';
            rex_install_archive::copyDirToArchive($path, $archive);
            if (is_dir($assets)) {
                rex_install_archive::copyDirToArchive($assets, $archive, 'assets');
            }
        }

        // ---- update main addon dir
        $pathOld = Path::addon($this->addonkey . '.old');
        error_clear_last();
        // move current addon to temp path
        if (!@rename($path, $pathOld)) {
            $message = $path . ' could not be moved to ' . $pathOld;
            $message .= ($error = error_get_last()) ? ': ' . $error['message'] : '.';
            throw new UserMessageException($message);
        }
        // move new addon to main addon path
        if (@rename($temppath, $path)) {
            // remove temp path of old addon
            Dir::delete($pathOld);
        } else {
            // revert to old addon
            rename($pathOld, $path);

            $message = $temppath . ' could not be moved to ' . $path;
            $message .= ($error = error_get_last()) ? ': ' . $error['message'] : '.';
            throw new UserMessageException($message);
        }

        // ---- update assets
        $origAssets = $this->addon->getPath('assets');
        if ($this->addon->isInstalled() && is_dir($origAssets)) {
            Dir::copy($origAssets, $assets);
        }

        AddonManager::synchronizeWithFileSystem();

        // ---- update package order
        if ($this->addon->isAvailable()) {
            $this->addon->loadProperties(true);
            AddonManager::generatePackageOrder();
        }

        $this->addon->setProperty('version', $this->file['version']);
        rex_install_packages::updatedPackage($this->addonkey, $this->fileId);

        Logger::factory()->info('AddOn ' . $this->addonkey . ' updated from ' . $oldVersion . ' to version ' . $this->file['version']);

        // re-generate opcache to make sure new/updated classes immediately are available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return null;
    }

    /** @return string|true */
    private function checkRequirements($config)
    {
        $temppath = Path::addon('.new.' . $this->addonkey);

        // ---- update "version", "requires" and "conflicts" properties
        /** @var SplObjectStorage<Addon, string> $versions */
        $versions = new SplObjectStorage();
        /** @var SplObjectStorage<Addon, array> $requirements */
        $requirements = new SplObjectStorage();
        /** @var SplObjectStorage<Addon, array> $conflicts */
        $conflicts = new SplObjectStorage();

        $requirements[$this->addon] = $this->addon->getProperty('requires', []);
        $this->addon->setProperty('requires', $config['requires'] ?? []);

        $conflicts[$this->addon] = $this->addon->getProperty('conflicts', []);
        $this->addon->setProperty('conflicts', $config['conflicts'] ?? []);

        $versions[$this->addon] = $this->addon->getVersion();
        $this->addon->setProperty('version', $config['version'] ?? $this->file['version']);

        // ---- check requirements
        $messages = [];
        $manager = AddonManager::factory($this->addon);
        if (!$manager->checkRequirements()) {
            $messages[] = $manager->getMessage();
        }
        if (!$manager->checkConflicts()) {
            $messages[] = $manager->getMessage();
        }

        if (empty($messages)) {
            foreach (Addon::getAvailableAddons() as $package) {
                if ($package === $this->addon) {
                    continue;
                }
                $manager = AddonManager::factory($package);
                if (!$manager->checkPackageRequirement($this->addon->getPackageId())) {
                    $messages[] = $this->messageFromPackage($package, $manager);
                }
            }
        }

        // ---- reset "version", "requires" and "conflicts" properties
        foreach ($versions as $package) {
            $package->setProperty('version', $versions[$package]);
        }
        foreach ($requirements as $package) {
            $package->setProperty('requires', $requirements[$package]);
        }
        foreach ($conflicts as $package) {
            $package->setProperty('conflicts', $conflicts[$package]);
        }

        return empty($messages) ? true : '<ul><li>' . implode('</li><li>', $messages) . '</li></ul>';
    }

    private function messageFromPackage(Addon $package, AddonManager $manager): string
    {
        return I18n::msg('install_warning_message_from_addon', $package->getPackageId()) . ' ' . $manager->getMessage();
    }
}
