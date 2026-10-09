<?php

namespace Redaxo\Core\Setup;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Migration\Migrator;
use Redaxo\Core\Translation\I18n;

use function Redaxo\Core\View\escape;

/**
 * @internal
 */
final class Importer
{
    private function __construct() {}

    public static function databaseAlreadyExists(): string
    {
        try {
            include Path::core('setup/install.php');
        } catch (UserMessageException $e) {
            return $e->getMessage();
        }

        return self::reinstallPackages();
    }

    public static function overrideExisting(): string
    {
        // ----- volle Datenbank, alte DB löschen / drop
        $errMsg = '';

        $db = Sql::factory();
        // the tables are referenced by foreign keys (e.g. `rex_language` by the translation tables) and are recreated
        // by the install right after
        $db->setQuery('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::getRequiredTables() as $table) {
            $db->setQuery('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $db->setQuery('SET FOREIGN_KEY_CHECKS = 1');

        try {
            include Path::core('setup/install.php');
        } catch (UserMessageException $e) {
            $errMsg .= $e->getMessage();
        }

        if ('' == $errMsg) {
            $errMsg .= self::reinstallPackages();
            self::baselineMigrations();
        }

        return $errMsg;
    }

    public static function prepareEmptyDb(): string
    {
        // ----- leere Datenbank neu einrichten
        $errMsg = '';

        try {
            include Path::core('setup/install.php');
        } catch (UserMessageException $e) {
            $errMsg .= $e->getMessage();
        }

        if ('' == $errMsg) {
            $errMsg .= self::reinstallPackages();
            self::baselineMigrations();
        }

        return $errMsg;
    }

    /**
     * Records all existing migrations as executed without running them: a fresh installation is built from the
     * current code. Addons are baselined individually when they are installed.
     */
    private static function baselineMigrations(): void
    {
        Migrator::baseline(Migrator::CORE);
        Migrator::baseline(Migrator::PROJECT);
    }

    public static function verifyDbSchema(): string
    {
        $errMsg = '';

        // Prüfen, welche Tabellen bereits vorhanden sind
        $existingTables = Sql::factory()->getTables(Core::TABLE_PREFIX);

        foreach (array_diff(self::getRequiredTables(), $existingTables) as $missingTable) {
            $errMsg .= I18n::msg('setup_402', $missingTable) . '<br />';
        }
        return $errMsg;
    }

    /** @return list<string> */
    private static function getRequiredTables(): array
    {
        return [
            'rex_language',
            'rex_user_session',
            'rex_user_passkey',
            'rex_user',
            'rex_config',
        ];
    }

    private static function reinstallPackages(): string
    {
        $error = '';
        Addon::initialize();

        // enlist activated packages to ensure that all their classess are known in autoloader and can be referenced in other package's install.php
        foreach (Addon::getBootOrder() as $packageId) {
            Addon::require($packageId)->enlist();
        }
        foreach (Addon::getBootOrder() as $packageId) {
            $package = Addon::require($packageId);
            $manager = AddonManager::factory($package);

            if (!$manager->install()) {
                $error .= '<li>' . escape($package->name) . '<ul><li>' . $manager->getMessage() . '</li></ul></li>';
            }
        }

        if ($error) {
            $error = '<ul class="rex-ul1">
            <li>
            <h3 class="rex-hl3">' . I18n::msg('setup_413') . '</h3>
            <ul>' . $error . '</ul>
            </li>
            </ul>';
        }

        // force to save config at this point
        Config::save();

        return $error;
    }
}
