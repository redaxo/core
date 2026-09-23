<?php

namespace Redaxo\Core;

use Composer\InstalledVersions;
use Redaxo\Core\Console\Application;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Validator\Validator;
use Symfony\Component\HttpClient\HttpClient as HttpClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

/**
 * Base class for core properties etc.
 */
final class Core
{
    public const string CONFIG_NAMESPACE = 'core';

    /** Prefix of all database tables. */
    public const string TABLE_PREFIX = 'rex_';

    /** Prefix marking temporary database tables and files. */
    public const string TEMP_PREFIX = 'tmp_';

    /**
     * Array of properties.
     *
     * @var array<string, mixed>
     */
    private static array $properties = [];

    private static ?AbstractProject $project = null;

    private static ?HttpClientInterface $httpClient = null;

    private static bool $invalidModeReported = false;

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
            case 'console':
                if (null !== $value && !$value instanceof Application) {
                    throw new InvalidArgumentException(sprintf('"%s" property: expecting $value to be an instance of %s, "%s" found.', $key, Application::class, get_debug_type($value)));
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
     *      ($key is 'timer' ? Timer :
     *      mixed|null
     *      ))
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

    /** Returns if the environment is the backend (the console counts as backend, too). */
    public static function isBackend(): bool
    {
        return Environment::Frontend !== self::getEnvironment();
    }

    /** Returns if the environment is the frontend. */
    public static function isFrontend(): bool
    {
        return Environment::Frontend === self::getEnvironment();
    }

    /** Returns the environment. */
    public static function getEnvironment(): Environment
    {
        if (self::getConsole()) {
            return Environment::Console;
        }

        return self::getProperty('redaxo', false) ? Environment::Backend : Environment::Frontend;
    }

    /**
     * Returns the mode this instance runs in, defined by the env var `REX_MODE` (usually in the `.env` file).
     *
     * When the env var is not defined at all, the fail-safe fallback is the live mode.
     *
     * @phpstan-impure
     */
    public static function getMode(): Mode
    {
        $mode = Env::get('REX_MODE');

        if (null === $mode) {
            return Mode::Live;
        }

        if (null === $modeEnum = Mode::tryFrom($mode)) {
            // Throw only on the first call and fall back to the (fail-safe) live mode afterwards, so that the
            // exception itself can be reported properly (the error handling asks for the mode, too).
            if (!self::$invalidModeReported) {
                self::$invalidModeReported = true;

                throw new LogicException(sprintf('The env var "REX_MODE" contains the invalid value "%s", it must be one of "dev", "live" or "hardened".', $mode));
            }

            return Mode::Live;
        }

        return $modeEnum;
    }

    /** Returns if the dev mode is active. */
    public static function isDevMode(): bool
    {
        return Mode::Dev === self::getMode();
    }

    /** Returns if the hardened mode is active. */
    public static function isHardenedMode(): bool
    {
        return Mode::Hardened === self::getMode();
    }

    /**
     * Returns the unique id of this installation, defined by the env var `REX_INSTANCE_ID` (usually in the `.env`
     * file).
     *
     * @return non-empty-string
     */
    public static function getInstanceId(): string
    {
        return Env::require('REX_INSTANCE_ID');
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

    /** @internal */
    public static function setProject(AbstractProject $project): void
    {
        self::$project = $project;
    }

    /** Returns the project this instance runs. */
    public static function getProject(): AbstractProject
    {
        return self::$project ?? throw new LogicException('The project is not available before it has booted the core.');
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

    /**
     * Returns a shared HTTP client for outgoing requests, backed by the Symfony HTTP client.
     *
     * The client honors the standard proxy environment variables (`HTTP_PROXY`, `HTTPS_PROXY`,
     * `NO_PROXY`) out of the box, so a global proxy is configured purely via the environment.
     */
    public static function getHttpClient(): HttpClientInterface
    {
        return self::$httpClient ??= HttpClientFactory::create([
            // Neutral User-Agent without version to avoid fingerprinting the installation
            'headers' => ['User-Agent' => 'REDAXO'],
        ]);
    }

    /**
     * Returns the absolute url of the frontend, with a trailing slash.
     *
     * It is defined by the env var `REX_BASE_URL`, otherwise derived from the current request (so it follows the
     * `Host` header then). On the console there is no request, so the env var is required there.
     *
     * @return non-empty-string
     */
    public static function getBaseUrl(): string
    {
        $url = Env::get('REX_BASE_URL');

        if (null !== $url) {
            if (!Validator::factory()->url($url)) {
                throw new InvalidArgumentException(sprintf('The env var "REX_BASE_URL" must be a full url, "%s" given.', $url));
            }

            return rtrim($url, '/') . '/';
        }

        $request = self::getProperty('request');
        if (!$request instanceof Request) {
            throw new LogicException('The env var "REX_BASE_URL" is missing, it is required to build absolute urls on the console.');
        }

        $path = $request->getBasePath();
        if (self::isBackend()) {
            $path = substr($path, 0, (int) strrpos($path, '/'));
        }

        return $request->getSchemeAndHttpHost() . $path . '/';
    }

    /**
     * Returns the name of this installation, defined by the env var `REX_INSTANCE_NAME` (usually in the `.env` file).
     *
     * @return non-empty-string
     */
    public static function getInstanceName(): string
    {
        return Env::get('REX_INSTANCE_NAME') ?? 'REDAXO';
    }

    /**
     * Returns the redaxo version.
     *
     * @param string $format See {@link Formatter::version()}
     */
    public static function getVersion(?string $format = null): string
    {
        $version = Type::string(InstalledVersions::getPrettyVersion('redaxo/core'));

        // On feature branches Composer returns "dev-<branch>", which is not
        // a meaningful version. Fall back to a generic dev version.
        if (str_starts_with($version, 'dev-')) {
            $version = '6.x-dev';
        }

        if ($format) {
            return Formatter::version($version, $format);
        }
        return $version;
    }
}
