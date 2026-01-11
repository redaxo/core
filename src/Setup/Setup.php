<?php

namespace Redaxo\Core\Setup;

use DateTimeImmutable;
use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Configuration;
use Redaxo\Core\Database\Exception\CouldNotConnectException;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Finder;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Util\Version;

use function array_key_exists;
use function extension_loaded;
use function function_exists;
use function in_array;
use function ini_get;
use function is_array;
use function sprintf;

use const PHP_OS;
use const PHP_SAPI;
use const PHP_VERSION;

/**
 * @internal
 */
class Setup
{
    // These values must be synchronized with the values in setup/update.php
    /** @var string */
    public const MIN_PHP_VERSION = REX_MIN_PHP_VERSION;
    public const MIN_PHP_EXTENSIONS = ['ctype', 'fileinfo', 'filter', 'gd', 'iconv', 'intl', 'mbstring', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer'];
    public const MIN_MYSQL_VERSION = '8.0';
    public const MIN_MARIADB_VERSION = '10.4';

    /**
     * no-password placeholder required to support empty passwords/clearing the password.
     */
    public const DEFAULT_DUMMY_PASSWORD = '-REDAXO-DEFAULT-DUMMY-PASSWORD-';

    public const DB_MODE_SETUP_NO_OVERRIDE = 0;
    public const DB_MODE_SETUP_AND_OVERRIDE = 1;
    public const DB_MODE_SETUP_SKIP = 2;
    public const DB_MODE_SETUP_IMPORT_BACKUP = 3;
    public const DB_MODE_SETUP_UPDATE_FROM_PREVIOUS = 4;

    /**
     * very basic setup steps, so everything is in place for our browser-based setup wizard.
     *
     * @return void
     */
    public static function init()
    {
        // initial purge all generated files
        Cache::delete();

        // copy alle media files of the current rex-version into redaxo_media
        Dir::copy(Path::core('assets'), Path::coreAssets());
        // in a regular release the folder will never be empty, because we ship it prefilled.
        // provide a error message for 'git cloned' sources, to give newcomers a hint why the very first setup might look broken.
        // we intentionally dont check permissions here, as those will be checked in a later setup step.
        if (!is_dir(Path::coreAssets())) {
            throw new RuntimeException('Unable to copy assets to "' . Path::coreAssets() . '". Is the folder writable for the webserver?');
        }

        $files = require Path::core('vendor_files.php');
        foreach ($files as $source => $destination) {
            // ignore errors, because this file is included very early in setup, before the regular file permissions check
            File::copy(Path::core('assets_files/' . $source), Path::coreAssets($destination));
        }
    }

    /**
     * checks environment related conditions.
     *
     * @return list<string> An array of error messages
     */
    public static function checkEnvironment()
    {
        $errors = [];

        // -------------------------- VERSIONSCHECK
        if (1 == version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $errors[] = I18n::msg('setup_201', PHP_VERSION, self::MIN_PHP_VERSION);
        }

        // -------------------------- EXTENSION CHECK
        foreach (self::MIN_PHP_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = I18n::msg('setup_202', $extension);
            }
        }

        return $errors;
    }

    /**
     * checks permissions of all required filesystem resources.
     *
     * @return array<string, non-empty-list<string>> An array of error messages
     */
    public static function checkFilesystem(): array
    {
        // -------------------------- SCHREIBRECHTE
        $writables = [
            Path::media(),
            Path::assets(),
            Path::cache(),
            Path::data(),
        ];

        $getMod = static function ($path) {
            return Type::string(substr(sprintf('%o', fileperms($path)), -3));
        };

        $func = static function (string $dir) use (&$func, $getMod) {
            if (!Dir::isWritable($dir)) {
                return ['setup_204' => [$dir]];
            }
            $res = [];
            foreach (Finder::factory($dir) as $path => $file) {
                if ($file->isDir()) {
                    $res = array_merge_recursive($res, $func($path));
                } elseif (!$file->isWritable()) {
                    $res['setup_205'][] = $path;
                } elseif (0 !== strcasecmp(substr(PHP_OS, 0, 3), 'WIN') && str_ends_with($getMod($path), '7')) {
                    // check the "other" filesystem-bit for "all" permission.
                    $res['setup_211'][] = $path;
                }
            }
            return $res;
        };

        $res = [];
        foreach ($writables as $dir) {
            @Dir::create($dir);
            if (@is_dir($dir)) {
                $res = array_merge_recursive($res, $func($dir));
            } else {
                $res['setup_206'][] = $dir;
            }
        }

        return $res;
    }

    /**
     * Checks the version of the connected database server.
     * When validation of the database configs succeeds the settings will be used for Sql class.
     *
     * @param array $config array of database config
     * @param bool $createDb Should the database be created, if it not exists
     *
     * @return string Error message
     */
    public static function checkDb($config, $createDb)
    {
        $dbConfig = new Configuration($config['db'][1]);

        $err = Sql::checkDbConnection($dbConfig->host, $dbConfig->login, $dbConfig->password, $dbConfig->name, $createDb, Sql::createSslOptions($dbConfig));

        if (true !== $err) {
            return $err;
        }

        // use given db config instead of saved config
        $orgDbConfig = Core::getProperty('db');
        try {
            Core::setProperty('db', $config['db']);
            $sql = Sql::factory();
            $type = $sql->getDbType();
            $version = $sql->getDbVersion();
        } finally {
            Core::setProperty('db', $orgDbConfig);
        }

        $minVersion = Sql::MARIADB === $type ? self::MIN_MARIADB_VERSION : self::MIN_MYSQL_VERSION;
        if (Version::compare($version, $minVersion, '<')) {
            return I18n::msg('sql_database_required_version', $type, $version, self::MIN_MYSQL_VERSION, self::MIN_MARIADB_VERSION);
        }

        return '';
    }

    /**
     * Basic php security checks. Returns a human readable strings on error.
     *
     * @return list<string>
     */
    public static function checkPhpSecurity()
    {
        $security = [];

        if (PHP_SAPI !== 'cli' && !Request::isHttps()) {
            $security[] = I18n::msg('setup_security_no_https');
        }

        if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules())) {
            $security[] = I18n::msg('setup_security_warn_mod_security');
        }

        if (ini_get('session.auto_start')) {
            $security[] = I18n::msg('setup_session_autostart_warning');
        }

        // Source: https://www.php.net/supported-versions.php, Security Support Until, set to 1st of month
        $deprecatedVersions = [
            '8.1' => '2025-12-01',
            '8.2' => '2026-12-01',
            '8.3' => '2027-12-01',
            '8.4' => '2028-12-01',
            '8.5' => '2029-12-01',
        ];

        $versionNumber = Formatter::version(PHP_VERSION, '%s.%s');

        if (array_key_exists($versionNumber, $deprecatedVersions)) {
            $deprecationDate = $deprecatedVersions[$versionNumber];
            $currentDate = date('Y-m-d');
            if ($currentDate > $deprecationDate) {
                $security[] = I18n::msg('setup_security_deprecated_php', PHP_VERSION);
            }
        }
        return $security;
    }

    /**
     * Basic database security checks. Returns a human readable strings on error.
     *
     * @return list<string>
     */
    public static function checkDbSecurity()
    {
        $sql = Sql::factory();
        $dbVersion = $sql->getDbVersion();
        $dbType = $sql->getDbType();
        $security = [];
        $currentDate = date('Y-m-d');

        if (Sql::MARIADB === $dbType) {
            // Deprecated versions and dates
            // Source: https://endoflife.date/mariadb, set to 1st of month
            $deprecatedVersions = [
                '10.1' => '2020-10-01',
                '10.2' => '2022-05-01',
                '10.3' => '2023-05-01',
                '10.4' => '2024-06-01',
                '10.5' => '2025-06-01',
                '10.6' => '2026-07-01', // LTS
                '10.7' => '2023-02-01',
                '10.8' => '2023-05-01',
                '10.9' => '2023-08-01',
                '10.10' => '2023-11-01',
                '10.11' => '2028-02-01', // LTS
                '11.0' => '2024-06-01',
                '11.1' => '2024-08-01',
                '11.2' => '2024-11-01',
                '11.3' => '2024-05-01',
                '11.4' => '2029-05-01', // LTS
                '11.5' => '2024-11-01',
                '11.6' => '2025-02-01',
                '11.7' => '2025-05-01',
                '11.8' => '2028-06-01',
                '12.0' => '2025-11-01',
                '12.1' => '2026-02-01',
            ];

            $versionNumber = Formatter::version($dbVersion, '%s.%s');
            if (array_key_exists($versionNumber, $deprecatedVersions)) {
                $deprecationDate = $deprecatedVersions[$versionNumber];
                if ($currentDate > $deprecationDate) {
                    $security[] = I18n::msg('setup_security_deprecated_mariadb', $dbVersion);
                }
            }
        } elseif (Sql::MYSQL === $dbType) {
            // Deprecated versions and dates
            // Source: https://en.wikipedia.org/wiki/MySQL#Release_history, set to 1st of month
            $deprecatedVersions = [
                '5.6' => '2021-12-01',
                '5.7' => '2023-10-01',
                '8.0' => '2026-04-01',
                '8.1' => '2023-10-01',
                '8.2' => '2024-01-01',
                '8.3' => '2024-04-01',
                '8.4' => '2032-04-01',
                '9.0' => '2024-10-01',
                '9.1' => '2025-01-01',
                '9.2' => '2025-04-01',
                '9.3' => '2025-07-01',
                '9.4' => '2025-10-01',
                '9.5' => '2026-01-01',
            ];

            $versionNumber = Formatter::version($dbVersion, '%s.%s');
            if (array_key_exists($versionNumber, $deprecatedVersions)) {
                $deprecationDate = $deprecatedVersions[$versionNumber];
                if ($currentDate > $deprecationDate) {
                    $security[] = I18n::msg('setup_security_deprecated_mysql', $dbVersion);
                }
            }
        }

        return $security;
    }

    /**
     * Returns true when we are running the very first setup for this instance.
     * Otherwise false is returned, e.g. when setup was re-started from the core/systems page.
     */
    public static function isInitialSetup(): bool
    {
        /** @var bool|null $initial */
        static $initial;

        if (null !== $initial) {
            return $initial;
        }

        try {
            $userSql = Sql::factory();
            $userSql->setQuery('select * from ' . Core::getTable('user') . ' LIMIT 1');

            return $initial = 0 == $userSql->getRows();
        } catch (CouldNotConnectException) {
            return $initial = true;
        } catch (SqlException $e) {
            $sql = $e->getSql();
            if ($sql && Sql::ERRNO_TABLE_OR_VIEW_DOESNT_EXIST === $sql->getErrno()) {
                return $initial = true;
            }
            throw $e;
        }
    }

    /**
     * @return string|false Single-User-Setup URL or `false` on failure
     */
    public static function startWithToken()
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $configFile = Path::coreData('config.yml');
        $config = File::getConfig($configFile);

        $config['setup'] = isset($config['setup']) && is_array($config['setup']) ? $config['setup'] : [];
        $config['setup'][$token] = new DateTimeImmutable('+1 hour')->format('Y-m-d H:i:s');

        if (!File::putConfig($configFile, $config)) {
            return false;
        }

        return Url::backendPage('setup', ['setup_token' => $token]);
    }

    public static function isEnabled(): bool
    {
        $setup = Core::getProperty('setup', false);

        if (!is_array($setup)) {
            // system wide setup
            return (bool) $setup;
        }

        $currentToken = self::getToken();

        if (!$currentToken && Core::isFrontend()) {
            // no token in url, fast fail in frontend
            // (in backend all existing tokens are revalidated below)
            return false;
        }

        // invalidate expired tokens
        $updated = false;
        foreach ($setup as $token => $expire) {
            if (strtotime((string) $expire) < time()) {
                unset($setup[$token]);
                $updated = true;
            }
        }

        if ($updated) {
            $configFile = Path::coreData('config.yml');
            $config = File::getConfig($configFile);
            $config['setup'] = $setup ?: false;
            File::putConfig($configFile, $config);
        }

        return isset($setup[$currentToken]);
    }

    public static function getContext(): Context
    {
        $context = new Context([
            'page' => 'setup',
            'lang' => Request::request('lang', 'string', ''),
            'step' => Request::request('step', 'int', 1),
        ]);

        if ($token = self::getToken()) {
            $context->setParam('setup_token', $token);
        }

        return $context;
    }

    /**
     * Mark the setup as completed.
     */
    public static function markSetupCompleted(): bool
    {
        $configFile = Path::coreData('config.yml');
        $config = array_merge(
            File::getConfig(Path::core('setup/default.config.yml')),
            File::getConfig($configFile),
        );

        if (is_array($config['setup'])) {
            // remove current token
            if ($token = self::getToken()) {
                unset($config['setup'][$token]);
            }

            // if array is empty now, convert it to global `false` value
            $config['setup'] = $config['setup'] ?: false;
        } else {
            $config['setup'] = false;
        }

        $configWritten = File::putConfig($configFile, $config);

        if ($configWritten) {
            File::delete(Path::coreCache('config.yml.cache'));
        }

        return $configWritten;
    }

    private static function getToken(): ?string
    {
        return Request::get('setup_token', 'string', null);
    }
}
