<?php

namespace Redaxo\Core;

use Composer\InstalledVersions;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Symfony\Component\HttpClient\HttpClient as HttpClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

/**
 * Central registry of the running instance: project, environment, mode, config, request and current user.
 */
final class Core
{
    public const string CONFIG_NAMESPACE = 'core';

    /** Prefix of all database tables. */
    public const string TABLE_PREFIX = 'rex_';

    /** Prefix marking temporary database tables and files. */
    public const string TEMP_PREFIX = 'tmp_';

    private static ?AbstractProject $project = null;
    private static ?Request $request = null;
    private static ?User $user = null;
    private static ?HttpClientInterface $httpClient = null;
    private static ?Mailer $mailer = null;

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
        return self::getProject()->environment;
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

    /** Returns the current user. */
    public static function getUser(): ?User
    {
        return self::$user;
    }

    /**
     * Returns the current user.
     *
     * In contrast to `getUser`, this method throw an exception if the user does not exist.
     */
    public static function requireUser(): User
    {
        return self::$user ?? throw new LogicException('User object does not exist');
    }

    /** @internal */
    public static function setUser(?User $user): void
    {
        self::$user = $user;
    }

    /** Returns the current impersonator user. */
    public static function getImpersonator(): ?User
    {
        return BackendLogin::getCurrent()?->getImpersonator();
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

    /** Returns the current http request, which is not available on the console. */
    public static function getRequest(): Request
    {
        return self::$request ?? throw new RuntimeException('The request object is not available on the console.');
    }

    /** Returns if the http request is available, which is not the case on the console. */
    public static function hasRequest(): bool
    {
        return null !== self::$request;
    }

    /** @internal */
    public static function setRequest(?Request $request): void
    {
        self::$request = $request;
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

    /** Returns the mailer, configured by the env var `MAILER_DSN` (see {@see Mailer}). */
    public static function getMailer(): Mailer
    {
        return self::$mailer ??= Mailer::fromEnv();
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
