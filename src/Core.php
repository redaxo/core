<?php

namespace Redaxo\Core;

use Composer\InstalledVersions;
use Redaxo\Core\Console\Application;
use Redaxo\Core\Database\Configuration as DatabaseConfiguration;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Validator\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Tag\TaggedValue;

use function constant;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

use const PHP_SESSION_ACTIVE;

/**
 * Base class for core properties etc.
 */
final class Core
{
    public const string CONFIG_NAMESPACE = 'core';

    private const CACHE_ENV_KEY = "\0rex_env_var\0";

    /**
     * Array of properties.
     *
     * @var array<string, mixed>
     */
    private static array $properties = [];

    private function __construct() {}

    /**
     * @see Config::set()
     *
     * @param string|array<string, mixed> $key The associated key or an associative array of key/value pairs
     * @param mixed $value The value to save
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    public static function setConfig(string|array $key, mixed $value = null): bool
    {
        return Config::set(self::CONFIG_NAMESPACE, $key, $value);
    }

    /**
     * @see Config::get()
     *
     * @template T as ?string
     * @param T $key The associated key
     * @param mixed $default Default return value if no associated-value can be found
     * @return (T is string ? mixed|null : array<string, mixed>) the value for $key or $default if $key cannot be found in the given $namespace
     */
    public static function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return Config::get(self::CONFIG_NAMESPACE, $key, $default);
    }

    /**
     * @see Config::has()
     *
     * @param string $key The associated key
     * @return bool TRUE if the key is set, otherwise FALSE
     */
    public static function hasConfig(string $key): bool
    {
        return Config::has(self::CONFIG_NAMESPACE, $key);
    }

    /**
     * @see Config::remove()
     *
     * @param string $key The associated key
     * @return bool TRUE if the value was found and removed, otherwise FALSE
     */
    public static function removeConfig(string $key): bool
    {
        return Config::remove(self::CONFIG_NAMESPACE, $key);
    }

    /**
     * Sets a property. Changes will not be persisted accross http request boundaries.
     *
     * @param string $key Key of the property
     * @param mixed $value Value for the property
     *
     * @throws InvalidArgumentException on invalid parameters
     *
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    public static function setProperty(string $key, mixed $value): bool
    {
        switch ($key) {
            case 'debug':
                // bc for boolean "debug" property
                if (!is_array($value)) {
                    $debug = self::getDebugFlags();
                    $debug['enabled'] = (bool) $value;
                    $value = $debug;
                }
                $value['enabled'] = isset($value['enabled']) && $value['enabled'];
                if (!isset($value['throw_always_exception']) || !$value['throw_always_exception']) {
                    $value['throw_always_exception'] = false;
                } elseif (is_array($value['throw_always_exception'])) {
                    $value['throw_always_exception'] = array_reduce($value['throw_always_exception'], static function ($result, $item): int {
                        if (is_string($item)) {
                            // $item is string, e.g. "E_WARNING"
                            $item = constant($item);
                        }

                        return $result | $item;
                    }, 0);
                }
                break;
            case 'server':
                if (!Validator::factory()->url($value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be a full URL.');
                }
                $value = rtrim($value, '/') . '/';
                break;
            case 'error_email':
                if (null !== $value && !Validator::factory()->email($value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be an email address.');
                }
                break;
            case 'console':
                if (null !== $value && !$value instanceof Application) {
                    throw new InvalidArgumentException(sprintf('"%s" property: expecting $value to be an instance of %s, "%s" found.', $key, Application::class, get_debug_type($value)));
                }
                break;
            case 'version':
                if (!is_string($value) || !preg_match('/^\d+(?:\.\d+)*(?:\.x)*(?:-\w+)?$/', $value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be a valid version string.');
                }
                break;
        }
        $exists = isset(self::$properties[$key]);
        self::$properties[$key] = $value;
        return $exists;
    }

    /**
     * Returns a property.
     *
     * @param string $key Key of the property
     * @param mixed $default Default value, will be returned if the property isn't set
     *
     * @return (
     *      $key is 'login' ? BackendLogin|null :
     *      ($key is 'live_mode' ? bool :
     *      ($key is 'safe_mode' ? bool :
     *      ($key is 'debug' ? array{enabled: bool, throw_always_exception: bool|int} :
     *      ($key is 'lang_fallback' ? string[] :
     *      ($key is 'use_accesskeys' ? bool :
     *      ($key is 'accesskeys' ? array<string, string> :
     *      ($key is 'editor' ? string|null :
     *      ($key is 'editor_basepath' ? string|null :
     *      ($key is 'timer' ? Timer :
     *      ($key is 'timezone' ? string :
     *      ($key is 'table_prefix' ? non-empty-string :
     *      ($key is 'temp_prefix' ? non-empty-string :
     *      ($key is 'version' ? string :
     *      ($key is 'server' ? string :
     *      ($key is 'servername' ? string :
     *      ($key is 'error_email' ? string :
     *      ($key is 'lang' ? non-empty-string :
     *      ($key is 'instname' ? non-empty-string :
     *      ($key is 'theme' ? string :
     *      ($key is 'start_page' ? non-empty-string :
     *      ($key is 'http_client_proxy' ? non-empty-string|null :
     *      ($key is 'password_policy' ? array<string, scalar> :
     *      ($key is 'backend_login_policy' ? array<string, bool|int> :
     *      ($key is 'db' ? array<int, string[]> :
     *      ($key is 'setup' ? bool|array<string, int> :
     *      ($key is 'setup_addons' ? non-empty-string[] :
     *      mixed|null
     *      )))))))))))))))))))))))))))
     * ) The value for $key or $default if $key cannot be found
     */
    public static function getProperty(string $key, mixed $default = null): mixed
    {
        /** @psalm-suppress MixedReturnStatement */
        return self::$properties[$key] ?? $default;
    }

    /**
     * Returns if a property is set.
     *
     * @param string $key Key of the property
     *
     * @return bool TRUE if the key is set, otherwise FALSE
     */
    public static function hasProperty(string $key): bool
    {
        return isset(self::$properties[$key]);
    }

    /**
     * Removes a property.
     *
     * @param string $key Key of the property
     * @return bool TRUE if the value was found and removed, otherwise FALSE
     */
    public static function removeProperty(string $key): bool
    {
        $exists = isset(self::$properties[$key]);
        unset(self::$properties[$key]);
        return $exists;
    }

    /** Returns if the setup is active. */
    public static function isSetup(): bool
    {
        return Setup::isEnabled();
    }

    /** Returns if the environment is the backend. */
    public static function isBackend(): bool
    {
        return (bool) self::getProperty('redaxo', false);
    }

    /** Returns if the environment is the frontend. */
    public static function isFrontend(): bool
    {
        if (self::getConsole()) {
            return false;
        }
        return !self::getProperty('redaxo', false);
    }

    /**
     * Returns the environment.
     *
     * @return 'console'|'backend'|'frontend'
     */
    public static function getEnvironment(): string
    {
        if (self::getConsole()) {
            return 'console';
        }

        return self::isBackend() ? 'backend' : 'frontend';
    }

    /** Returns if the debug mode is active. */
    public static function isDebugMode(): bool
    {
        if (self::isLiveMode()) {
            return false;
        }

        $debug = self::getDebugFlags();

        return $debug['enabled'];
    }

    /**
     * Returns the debug flags.
     *
     * @return array{enabled: bool, throw_always_exception: bool|int}
     */
    public static function getDebugFlags(): array
    {
        $flags = self::getProperty('debug', []);

        $flags['enabled'] ??= false;
        $flags['throw_always_exception'] ??= false;

        return $flags;
    }

    /** Returns if the safe mode is active. */
    public static function isSafeMode(): bool
    {
        if (!self::isBackend() || self::isLiveMode()) {
            return false;
        }

        if (self::getProperty('safe_mode')) {
            return true;
        }

        return PHP_SESSION_ACTIVE == session_status() && Http\Request::session('safemode', 'boolean', false);
    }

    /** Returns if the live mode is active. */
    public static function isLiveMode(): bool
    {
        return (bool) self::getProperty('live_mode');
    }

    /**
     * Returns the table prefix.
     *
     * @return non-empty-string
     *
     * @phpstandba-inference-placeholder 'rex_'
     * @psalm-taint-escape sql
     */
    public static function getTablePrefix(): string
    {
        return self::getProperty('table_prefix');
    }

    /**
     * Adds the table prefix to the table name.
     *
     * @param non-empty-string $table Table name
     *
     * @return non-empty-string
     */
    public static function getTable(string $table): string
    {
        return self::getTablePrefix() . $table;
    }

    /**
     * Returns the temp prefix.
     *
     * @return non-empty-string
     *
     * @phpstandba-inference-placeholder 'tmp_'
     * @psalm-taint-escape sql
     */
    public static function getTempPrefix(): string
    {
        return self::getProperty('temp_prefix');
    }

    /** Returns the current user. */
    public static function getUser(): ?User
    {
        return self::getProperty('user');
    }

    /**
     * Returns the current user.
     *
     * In contrast to `getUser`, this method throw an exception if the user does not exist.
     */
    public static function requireUser(): User
    {
        $user = self::getProperty('user');

        if (!$user instanceof User) {
            throw new LogicException('User object does not exist');
        }

        return $user;
    }

    /** Returns the current impersonator user. */
    public static function getImpersonator(): ?User
    {
        $login = self::$properties['login'] ?? null;

        return $login ? $login->getImpersonator() : null;
    }

    /** Returns the console application. */
    public static function getConsole(): ?Application
    {
        return self::getProperty('console', null);
    }

    public static function getRequest(): Request
    {
        $request = self::getProperty('request');

        if (null === $request) {
            throw new RuntimeException('The request object is not available in cli');
        }

        return $request;
    }

    /** @param positive-int $db */
    public static function getDbConfig(int $db = 1): DatabaseConfiguration
    {
        $config = self::getProperty('db', null);

        if (!$config) {
            $configFile = Path::coreData('config.yml');

            throw new RuntimeException('Unable to read db config from "' . $configFile . '".');
        }

        return new DatabaseConfiguration($config[$db]);
    }

    /** Returns the server URL. */
    public static function getServer(?string $protocol = null): string
    {
        if (null === $protocol) {
            return self::getProperty('server');
        }
        [, $server] = explode('://', self::getProperty('server'), 2);
        return $protocol ? $protocol . '://' . $server : $server;
    }

    /** Returns the server name. */
    public static function getServerName(): string
    {
        return self::getProperty('servername');
    }

    /** Returns the error email. */
    public static function getErrorEmail(): string
    {
        return self::getProperty('error_email');
    }

    /**
     * Returns the redaxo version.
     *
     * @param string $format See {@link Formatter::version()}
     */
    public static function getVersion(?string $format = null): string
    {
        /** @psalm-taint-escape file */
        $version = self::getProperty('version');

        if (!$version) {
            $version = Type::string(InstalledVersions::getPrettyVersion('redaxo/core'));
            self::setProperty('version', $version);
        }

        if ($format) {
            return Formatter::version($version, $format);
        }
        return $version;
    }

    /**
     * @return array<non-empty-string, array{install: bool, status: bool}>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public static function getPackageConfig(): array
    {
        return Type::array(self::getConfig('package-config', []));
    }

    /** @return list<non-empty-string> */
    public static function getPackageOrder(): array
    {
        return Type::array(self::getConfig('package-order', []));
    }

    /**
     * Returns the title tag and if the property "use_accesskeys" is true, the accesskey tag.
     *
     * @param string $title Title
     * @param string $key Key for the accesskey
     * @return non-empty-string
     */
    public static function getAccesskey(string $title, string $key): string
    {
        if (self::getProperty('use_accesskeys')) {
            $accesskeys = (array) self::getProperty('accesskeys', []);
            if (isset($accesskeys[$key])) {
                return ' accesskey="' . $accesskeys[$key] . '" title="' . $title . ' [' . $accesskeys[$key] . ']"';
            }
        }

        return ' title="' . $title . '"';
    }

    /** Returns the file perm. */
    public static function getFilePerm(): int
    {
        return (int) self::getProperty('fileperm', 0o664);
    }

    /** Returns the dir perm. */
    public static function getDirPerm(): int
    {
        return (int) self::getProperty('dirperm', 0o775);
    }

    /**
     * Returns the current backend theme.
     *
     * @return 'dark'|'light'|null
     */
    public static function getTheme(): ?string
    {
        $themes = ['light', 'dark'];

        // global theme from config.yml
        $globalTheme = (string) self::getProperty('theme');
        if (in_array($globalTheme, $themes, true)) {
            return $globalTheme;
        }

        $user = self::getUser();
        if (!$user) {
            return null;
        }

        // user selected theme
        $userTheme = (string) $user->getValue('theme');
        if (in_array($userTheme, $themes, true)) {
            return $userTheme;
        }

        return null;
    }

    /** @internal */
    public static function loadConfigYml(): void
    {
        $cacheFile = Path::coreCache('config.yml.cache');
        $configFile = Path::coreData('config.yml');

        $cacheMtime = @filemtime($cacheFile);
        if ($cacheMtime && $cacheMtime >= @filemtime($configFile)) {
            $config = File::getCache($cacheFile);
        } else {
            $config = array_merge(
                File::getConfig(Path::core('setup/default.config.yml')),
                File::getConfig($configFile),
            );
            $config = array_map(static fn (mixed $value) => self::convertYamlTags($value), $config);
            File::putCache($cacheFile, $config);
        }

        /**
         * @var string $key
         * @var mixed $value
         */
        foreach ($config as $key => $value) {
            /** @psalm-suppress MixedAssignment */
            $value = self::convertEnvVariables($value);

            if (in_array($key, ['fileperm', 'dirperm'])) {
                $value = octdec((string) $value);
            }

            self::setProperty($key, $value);
        }
    }

    private static function convertYamlTags(mixed $value): mixed
    {
        if ($value instanceof TaggedValue) {
            if ('env' !== $value->getTag()) {
                return $value->getValue();
            }

            return [self::CACHE_ENV_KEY => $value->getValue()];
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map(static fn (mixed $value) => self::convertYamlTags($value), $value);
    }

    private static function convertEnvVariables(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $var = $value[self::CACHE_ENV_KEY] ?? null;
        if (!is_string($var)) {
            return array_map(static fn (mixed $value) => self::convertEnvVariables($value), $value);
        }

        $value = $_SERVER[$var] ?? $_ENV[$var] ?? null;

        if (null === $value) {
            throw new InvalidArgumentException('Environment variable "' . $var . '" is not set.');
        }

        return $value;
    }
}
