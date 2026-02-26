<?php

/**
 * @package redaxo\core
 *
 * @internal
 */
class rex_setup_importer
{
    /** @return string */
    public static function updateFromPrevious()
    {
        // ----- vorhandenen seite updaten
        $errMsg = '';

        $version = rex::getVersion();
        rex::setProperty('version', rex::getConfig('version'));

        try {
            include rex_path::core('update.php');
        } catch (rex_functional_exception $e) {
            $errMsg .= $e->getMessage();
        } catch (rex_sql_exception $e) {
            $errMsg .= 'SQL error: ' . $e->getMessage();
        }

        rex::setProperty('version', $version);

        if ('' == $errMsg) {
            $errMsg .= self::reinstallPackages();
        }

        return $errMsg;
    }

    /**
     * @param string $importName
     *
     * @return string
     */
    public static function loadExistingImport($importName)
    {
        if ('' == $importName || '/' === $importName) {
            return '<p>' . rex_i18n::msg('setup_408') . '</p>';
        }

        // ----- vorhandenen Export importieren
        $errMsg = '';
        $importName = rex_path::basename($importName);

        $importSql = rex_backup::getDir() . '/' . $importName . '.sql';
        $importSql .= is_file($importSql) ? '' : '.gz';
        $importArchiv = rex_backup::getDir() . '/' . $importName . '.tar.gz';

        // Nur hier zuerst die Addons installieren
        // Da sonst Daten aus dem eingespielten Export
        // Überschrieben würden
        // Da für das Installieren der Addons die rex_config benötigt wird,
        // mit overrideExisting() eine saubere, komplette Basis schaffen
        $errMsg .= self::overrideExisting();

        if ('' == $errMsg) {
            $errMsg .= self::import($importSql, $importArchiv);
        }

        return $errMsg;
    }

    /** @return string */
    public static function databaseAlreadyExists()
    {
        // ----- db schon vorhanden, nichts tun
        return self::reinstallPackages();
    }

    /** @return string */
    public static function overrideExisting()
    {
        // ----- volle Datenbank, alte DB löschen / drop
        $errMsg = '';

        $db = rex_sql::factory();
        foreach (self::getRequiredTables() as $table) {
            $db->setQuery('DROP TABLE IF EXISTS `' . $table . '`');
        }

        try {
            include rex_path::core('install.php');
        } catch (rex_functional_exception $e) {
            $errMsg .= $e->getMessage();
        } catch (rex_sql_exception $e) {
            $errMsg .= 'SQL error: ' . $e->getMessage();
        }

        if ('' == $errMsg) {
            $errMsg .= self::installAddons(true);
        }

        return $errMsg;
    }

    /** @return string */
    public static function prepareEmptyDb()
    {
        // ----- leere Datenbank neu einrichten
        $errMsg = '';

        try {
            include rex_path::core('install.php');
        } catch (rex_functional_exception $e) {
            $errMsg .= $e->getMessage();
        } catch (rex_sql_exception $e) {
            $errMsg .= 'SQL error: ' . $e->getMessage();
        }

        $errMsg .= self::installAddons();

        return $errMsg;
    }

    /** @return string */
    public static function verifyDbSchema()
    {
        $errMsg = '';

        // Prüfen, welche Tabellen bereits vorhanden sind
        $existingTables = rex_sql::factory()->getTables(rex::getTablePrefix());

        foreach (array_diff(self::getRequiredTables(), $existingTables) as $missingTable) {
            $errMsg .= rex_i18n::msg('setup_402', $missingTable) . '<br />';
        }
        return $errMsg;
    }

    public static function supportsUtf8mb4(): bool
    {
        $sql = rex_sql::factory();

        return version_compare($sql->getDbVersion(), match ($sql->getDbType()) {
            rex_sql::MYSQL => '5.7.7',
            rex_sql::MARIADB => '10.2.0',
        }, '>=');
    }

    /** @return list<string> */
    private static function getRequiredTables()
    {
        return [
            rex::getTablePrefix() . 'clang',
            rex::getTablePrefix() . 'user_session',
            rex::getTablePrefix() . 'user_passkey',
            rex::getTablePrefix() . 'user',
            rex::getTablePrefix() . 'config',
        ];
    }

    private static function import(string $importSql, ?string $importArchive = null): string
    {
        $errMsg = '';

        if (!is_dir(rex_path::addon('backup'))) {
            $errMsg .= rex_i18n::msg('setup_410') . '<br />';
        } else {
            if (is_file($importSql)) {
                rex_i18n::addDirectory(rex_path::addon('backup', 'lang/'));

                // DB Import
                $stateDb = rex_backup::importDb($importSql);
                if (!$stateDb['state']) {
                    $errMsg .= nl2br($stateDb['message']) . '<br />';
                }

                // Archiv optional importieren
                if ($stateDb['state'] && null !== $importArchive && is_file($importArchive)) {
                    $stateArchiv = rex_backup::importFiles($importArchive);
                    if (!$stateArchiv['state']) {
                        $errMsg .= $stateArchiv['message'] . '<br />';
                    }
                }
            } else {
                $errMsg .= rex_i18n::msg('setup_409') . '<br />';
            }
        }

        // Reload config from imported data
        rex_config::refresh();

        return $errMsg;
    }

    // -------------------------- System AddOns prüfen

    private static function installAddons(bool $uninstallBefore = false, bool $installDump = true): string
    {
        $addonErr = '';
        rex_package_manager::synchronizeWithFileSystem();

        if ($uninstallBefore) {
            foreach (array_reverse(rex_package::getSystemPackages()) as $package) {
                $manager = rex_package_manager::factory($package);
                $state = $manager->uninstall($installDump);

                if (!$state) {
                    $addonErr .= '<li>' . $package->getPackageId() . '<ul><li>' . $manager->getMessage() . '</li></ul></li>';
                }
            }
        }
        foreach (rex::getProperty('system_addons') as $packageRepresentation) {
            $state = true;
            $package = rex_package::require($packageRepresentation);
            $manager = rex_package_manager::factory($package);

            if (!$package->isInstalled()) {
                $state = $manager->install($installDump);
            }

            if (!$state) {
                $addonErr .= '<li>' . rex_escape($package->getPackageId()) . '<ul><li>' . $manager->getMessage() . '</li></ul></li>';
            }

            if ($state && !$package->isAvailable()) {
                $state = $manager->activate();

                if (!$state) {
                    $addonErr .= '<li>' . rex_escape($package->getPackageId()) . '<ul><li>' . $manager->getMessage() . '</li></ul></li>';
                }
            }
        }

        if ('' != $addonErr) {
            $addonErr = '<ul class="rex-ul1">
            <li>
            <h3 class="rex-hl3">' . rex_i18n::msg('setup_413') . '</h3>
            <ul>' . $addonErr . '</ul>
            </li>
            </ul>';
        }

        // force to save config at this point
        // otherwise it would be saved in shutdown function and maybe would replace config changes made by db import in between
        rex_config::save();

        return $addonErr;
    }

    private static function reinstallPackages(): string
    {
        $error = '';
        rex_addon::initialize();
        rex_package_manager::synchronizeWithFileSystem();

        // enlist activated packages to ensure that all their classess are known in autoloader and can be referenced in other package's install.php
        foreach (rex::getPackageOrder() as $packageId) {
            rex_package::require($packageId)->enlist();
        }
        foreach (rex::getPackageOrder() as $packageId) {
            $package = rex_package::require($packageId);
            $manager = rex_package_manager::factory($package);

            if (!$manager->install()) {
                $error .= '<li>' . rex_escape($package->getPackageId()) . '<ul><li>' . $manager->getMessage() . '</li></ul></li>';
            }
        }

        if ($error) {
            $error = '<ul class="rex-ul1">
            <li>
            <h3 class="rex-hl3">' . rex_i18n::msg('setup_413') . '</h3>
            <ul>' . $error . '</ul>
            </li>
            </ul>';
        }

        // force to save config at this point
        // otherwise it would be saved in shutdown function and maybe would replace config changes made by db import in between
        rex_config::save();

        return $error;
    }
}
