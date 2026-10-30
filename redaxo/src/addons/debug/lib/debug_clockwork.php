<?php

use Clockwork\Clockwork;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;

/**
 * @package redaxo\debug
 *
 * @internal
 */
class rex_debug_clockwork
{
    /** @var VanillaClockwork|null */
    private static $instance;

    /** @psalm-assert VanillaClockwork self::$instance */
    private static function init(): void
    {
        $clockwork = VanillaClockwork::init([
            'storage_files_path' => self::getStoragePath(),
            'storage_files_compress' => true,

            // there is a probability from 1 to 100 that the cleanup mechanism will be triggered and files older than 2 days will be removed
            'storage_expiration' => 60 * 24 * 2,
        ]);
        if (extension_loaded('xdebug')) {
            $clockwork->getClockwork()->addDataSource(new XdebugDataSource());
        }

        self::$instance = $clockwork;
    }

    public static function getInstance(): Clockwork
    {
        return self::getHelper()->getClockwork();
    }

    public static function getHelper(): VanillaClockwork
    {
        if (!self::$instance) {
            self::init();
        }
        return self::$instance;
    }

    public static function getFullClockworkApiUrl(): string
    {
        $https = isset($_SERVER['HTTPS']) && 'on' == $_SERVER['HTTPS'];
        $host = $_SERVER['HTTP_HOST'];
        $port = $_SERVER['SERVER_PORT'] ?? null;
        $uri = dirname($_SERVER['REQUEST_URI']) . '/' . self::getClockworkApiUrl();

        $scheme = $https ? 'https' : 'http';
        $port = (!$https && 80 != $port || $https && 443 != $port) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$port}{$uri}";
    }

    public static function getClockworkApiUrl(): string
    {
        return rex_url::backendPage('debug', rex_api_debug::getUrlParams(), false);
    }

    /** @return void */
    public static function ensureStoragePath()
    {
        $storagePath = self::getStoragePath();
        if (!is_dir($storagePath)) {
            rex_dir::create($storagePath);
        }
    }

    /** @return string */
    public static function getStoragePath()
    {
        return rex_addon::get('debug')->getCachePath('clockwork.db');
    }

    /**
     * We cannot rely on rex::isDebugMode() because it is always true on the console.
     * So we have to check the config file itself.
     */
    public static function isRexDebugEnabled(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return rex::isDebugMode();
        }

        $coreConfigCacheFile = rex_path::coreCache('config.yml.cache');
        $coreConfigCache = rex_file::getCache($coreConfigCacheFile);
        /** @var bool $debugEnabled */
        $debugEnabled = $coreConfigCache['debug']['enabled'] ?? false;

        return $debugEnabled;
    }

    /**
     * Returns the console arguments or options that differ from their defaults, for the command in Clockwork.
     *
     * Each value is compared with the default of its own argument or option. Array values (arguments and options that
     * accept several values) are compared as arrays, all other values as strings.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $defaults
     * @return array<array-key, mixed>
     *
     * @internal
     */
    public static function getChangedInputValues(array $values, array $defaults): array
    {
        // like array_diff(): null and false count as '', true as '1'
        $text = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';

        return array_filter($values, static function (mixed $value, string|int $name) use ($defaults, $text): bool {
            if (!array_key_exists($name, $defaults)) {
                return true;
            }
            if (is_array($value) || is_array($defaults[$name])) {
                return $value !== $defaults[$name];
            }

            return $text($value) !== $text($defaults[$name]);
        }, ARRAY_FILTER_USE_BOTH);
    }
}
