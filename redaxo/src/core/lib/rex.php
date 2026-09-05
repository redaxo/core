<?php

use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * REX base class for core properties etc.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex
{
    public const CONFIG_NAMESPACE = 'core';

    private const CACHE_ENV_KEY = "\0rex_env_var\0";

    /**
     * Array of properties.
     *
     * @var array<string, mixed>
     */
    protected static $properties = [];

    /**
     * @see rex_config::set()
     *
     * @param string|array<string, mixed> $key The associated key or an associative array of key/value pairs
     * @param mixed $value The value to save
     * @return bool TRUE when an existing value was overridden, otherwise FALSE
     */
    public static function setConfig($key, $value = null)
    {
        return rex_config::set(self::CONFIG_NAMESPACE, $key, $value);
    }

    /**
     * @see rex_config::get()
     *
     * @template T as ?string
     * @param T $key The associated key
     * @param mixed $default Default return value if no associated-value can be found
     * @throws InvalidArgumentException
     * @return (T is string ? mixed|null : array<string, mixed>) the value for $key or $default if $key cannot be found in the given $namespace
     */
    public static function getConfig($key = null, $default = null)
    {
        return rex_config::get(self::CONFIG_NAMESPACE, $key, $default);
    }

    /**
     * @see rex_config::has()
     *
     * @param string $key The associated key
     * @return bool TRUE if the key is set, otherwise FALSE
     */
    public static function hasConfig($key)
    {
        return rex_config::has(self::CONFIG_NAMESPACE, $key);
    }

    /**
     * @see rex_config::remove()
     *
     * @param string $key The associated key
     * @return bool TRUE if the value was found and removed, otherwise FALSE
     */
    public static function removeConfig($key)
    {
        return rex_config::remove(self::CONFIG_NAMESPACE, $key);
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
    public static function setProperty($key, $value)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expecting $key to be string, but ' . gettype($key) . ' given!');
        }

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
                    $value['throw_always_exception'] = array_reduce($value['throw_always_exception'], static function (int $result, $item): int {
                        if (is_string($item)) {
                            // $item is string, e.g. "E_WARNING"
                            $item = constant($item);
                        }

                        return $result | rex_type::int($item);
                    }, 0);
                }
                break;
            case 'server':
                if (!rex_validator::factory()->url($value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be a full URL!');
                }
                $value = rtrim($value, '/') . '/';
                break;
            case 'error_email':
                if (null !== $value && !rex_validator::factory()->email($value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be an email address!');
                }
                break;
            case 'console':
                if (null !== $value && !$value instanceof rex_console_application) {
                    throw new InvalidArgumentException(sprintf('"%s" property: expecting $value to be an instance of rex_console_application, "%s" found!', $key, get_debug_type($value)));
                }
                break;
            case 'version':
                if (!is_string($value) || !preg_match('/^\d+(?:\.\d+)*(?:-\w+)?$/', $value)) {
                    throw new InvalidArgumentException('"' . $key . '" property: expecting $value to be a valid version string');
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
     * @throws InvalidArgumentException on invalid parameters
     *
     * @return (
     *     $key is 'login' ? rex_backend_login|null :
     *     ($key is 'live_mode' ? bool :
     *     ($key is 'safe_mode' ? bool :
     *     ($key is 'debug' ? array{enabled: bool, throw_always_exception: bool|int} :
     *     ($key is 'lang_fallback' ? string[] :
     *     ($key is 'use_accesskeys' ? bool :
     *     ($key is 'use_directory_protection_check' ? bool :
     *     ($key is 'accesskeys' ? array<string, string> :
     *     ($key is 'editor' ? string|null :
     *     ($key is 'editor_basepath' ? string|null :
     *     ($key is 'timer' ? rex_timer :
     *     ($key is 'timezone' ? string :
     *     ($key is 'table_prefix' ? non-empty-string :
     *     ($key is 'temp_prefix' ? non-empty-string :
     *     ($key is 'version' ? string :
     *     ($key is 'server' ? string :
     *     ($key is 'servername' ? string :
     *     ($key is 'error_email' ? string :
     *     ($key is 'lang' ? non-empty-string :
     *     ($key is 'instname' ? non-empty-string :
     *     ($key is 'theme' ? string :
     *     ($key is 'start_page' ? non-empty-string :
     *     ($key is 'socket_proxy' ? non-empty-string|null :
     *     ($key is 'password_policy' ? array<string, scalar> :
     *     ($key is 'backend_login_policy' ? array<string, bool|int> :
     *     ($key is 'db' ? array<int, string[]> :
     *     ($key is 'setup' ? bool|array<string, int> :
     *     ($key is 'system_addons' ? non-empty-string[] :
     *     ($key is 'setup_addons' ? non-empty-string[] :
     *     mixed|null
     *     ))))))))))))))))))))))))))))
     * ) The value for $key or $default if $key cannot be found
     */
    public static function getProperty($key, $default = null)
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expecting $key to be string, but ' . gettype($key) . ' given!');
        }
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
    public static function hasProperty($key)
    {
        return is_string($key) && isset(self::$properties[$key]);
    }

    /**
     * Removes a property.
     *
     * @param string $key Key of the property
     *
     * @throws InvalidArgumentException on invalid parameters
     *
     * @return bool TRUE if the value was found and removed, otherwise FALSE
     */
    public static function removeProperty($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expecting $key to be string, but ' . gettype($key) . ' given!');
        }
        $exists = isset(self::$properties[$key]);
        unset(self::$properties[$key]);
        return $exists;
    }

    /**
     * Returns if the setup is active.
     *
     * @return bool
     */
    public static function isSetup()
    {
        return rex_setup::isEnabled();
    }

    /**
     * Returns if the environment is the backend.
     *
     * @return bool
     */
    public static function isBackend()
    {
        return (bool) self::getProperty('redaxo', false);
    }

    /**
     * Returns if the environment is the frontend.
     *
     * @return bool
     */
    public static function isFrontend()
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
    public static function getEnvironment()
    {
        if (self::getConsole()) {
            return 'console';
        }

        return self::isBackend() ? 'backend' : 'frontend';
    }

    /**
     * Returns if the debug mode is active.
     *
     * @return bool
     */
    public static function isDebugMode()
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
    public static function getDebugFlags()
    {
        $flags = self::getProperty('debug', []);

        $flags['enabled'] ??= false;
        $flags['throw_always_exception'] ??= false;

        return $flags;
    }

    /**
     * Returns if the safe mode is active.
     *
     * @return bool
     */
    public static function isSafeMode()
    {
        if (!self::isBackend() || self::isLiveMode()) {
            return false;
        }

        if (self::getProperty('safe_mode')) {
            return true;
        }

        return PHP_SESSION_ACTIVE == session_status() && rex_session('safemode', 'boolean', false);
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
    public static function getTablePrefix()
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
    public static function getTable($table)
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
    public static function getTempPrefix()
    {
        return self::getProperty('temp_prefix');
    }

    /**
     * Returns the current user.
     *
     * @return rex_user|null
     */
    public static function getUser()
    {
        return self::getProperty('user');
    }

    /**
     * Returns the current user.
     *
     * In contrast to `getUser`, this method throw a `rex_exception` if the user does not exist.
     */
    public static function requireUser(): rex_user
    {
        $user = self::getProperty('user');

        if (!$user instanceof rex_user) {
            throw new rex_exception('User object does not exist');
        }

        return $user;
    }

    /**
     * Returns the current impersonator user.
     *
     * @return rex_user|null
     */
    public static function getImpersonator()
    {
        $login = self::$properties['login'] ?? null;

        return $login ? $login->getImpersonator() : null;
    }

    /**
     * Returns the console application.
     *
     * @return rex_console_application|null
     */
    public static function getConsole()
    {
        return self::getProperty('console', null);
    }

    public static function getRequest(): Request
    {
        $request = self::getProperty('request');

        if (null === $request) {
            throw new rex_exception('The request object is not available in cli');
        }

        return $request;
    }

    /**
     * @param positive-int $db
     *
     * @throws rex_exception
     */
    public static function getDbConfig(int $db = 1): rex_config_db
    {
        $config = self::getProperty('db', null);

        if (!$config) {
            $configFile = rex_path::coreData('config.yml');

            throw new rex_exception('Unable to read db config from config.yml "' . $configFile . '"');
        }

        return new rex_config_db($config[$db]);
    }

    /**
     * Returns the server URL.
     *
     * @param string|null $protocol
     *
     * @return string
     */
    public static function getServer($protocol = null)
    {
        if (null === $protocol) {
            return self::getProperty('server');
        }
        [, $server] = explode('://', self::getProperty('server'), 2);
        return $protocol ? $protocol . '://' . $server : $server;
    }

    /**
     * Returns the server name.
     *
     * @return string
     */
    public static function getServerName()
    {
        return self::getProperty('servername');
    }

    /**
     * Returns the error email.
     *
     * @return string
     */
    public static function getErrorEmail()
    {
        return self::getProperty('error_email');
    }

    /**
     * Returns the redaxo version.
     *
     * @param string $format See {@link rex_formatter::version()}
     *
     * @return string
     */
    public static function getVersion($format = null)
    {
        /** @psalm-taint-escape file */
        $version = self::getProperty('version');

        if ($format) {
            return rex_formatter::version($version, $format);
        }
        return $version;
    }

    /**
     * @deprecated since 5.10, use `rex_version::gitHash` instead
     * @param string $path
     * @return non-empty-string|false
     */
    #[Deprecated(reason: 'since 5.10, use `rex_version::gitHash` instead', replacement: 'rex_version::gitHash(%parametersList%)')]
    public static function getVersionHash($path, ?string $repo = null)
    {
        return rex_version::gitHash($path, $repo) ?? false;
    }

    /**
     * @return array<non-empty-string, array{install: bool, status: bool, plugins?: array<string, array{install: bool, status: bool}>}>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public static function getPackageConfig(): array
    {
        return rex_type::array(self::getConfig('package-config', []));
    }

    /** @return list<non-empty-string> */
    public static function getPackageOrder(): array
    {
        return rex_type::array(self::getConfig('package-order', []));
    }

    /**
     * Returns the title tag and if the property "use_accesskeys" is true, the accesskey tag.
     *
     * @param string $title Title
     * @param string $key Key for the accesskey
     *
     * @return string
     */
    public static function getAccesskey($title, $key)
    {
        if (self::getProperty('use_accesskeys')) {
            $accesskeys = (array) self::getProperty('accesskeys', []);
            if (isset($accesskeys[$key])) {
                return ' accesskey="' . $accesskeys[$key] . '" title="' . $title . ' [' . $accesskeys[$key] . ']"';
            }
        }

        return ' title="' . $title . '"';
    }

    /**
     * Returns the file perm.
     *
     * @return int
     */
    public static function getFilePerm()
    {
        return (int) self::getProperty('fileperm', 0o664);
    }

    /**
     * Returns the dir perm.
     *
     * @return int
     */
    public static function getDirPerm()
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
        $cacheFile = rex_path::coreCache('config.yml.cache');
        $configFile = rex_path::coreData('config.yml');

        $cacheMtime = @filemtime($cacheFile);
        if ($cacheMtime && $cacheMtime >= @filemtime($configFile)) {
            $config = rex_file::getCache($cacheFile);
        } else {
            $config = array_merge(
                rex_file::getConfig(rex_path::core('default.config.yml')),
                rex_file::getConfig($configFile),
            );
            $config = array_map(static fn (mixed $value) => self::convertYamlTags($value), $config);
            rex_file::putCache($cacheFile, $config);
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
