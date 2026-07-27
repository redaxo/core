<?php

use Clockwork\Clockwork;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Request;
use Clockwork\Support\Vanilla\Clockwork as VanillaClockwork;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Util\Type;

/**
 * @internal
 */
final class rex_debug_clockwork
{
    private static ?VanillaClockwork $instance = null;

    private static function init(): VanillaClockwork
    {
        /** @var VanillaClockwork $clockwork */
        $clockwork = VanillaClockwork::init([
            'storage_files_path' => self::getStoragePath(),
            'storage_files_compress' => true,

            // there is a probability from 1 to 100 that the cleanup mechanism will be triggered and files older than 2 days will be removed
            'storage_expiration' => 60 * 24 * 2,
        ]);
        if (extension_loaded('xdebug')) {
            /** @var Clockwork $instance */
            $instance = $clockwork->getClockwork();
            $instance->addDataSource(new XdebugDataSource());
        }

        return $clockwork;
    }

    public static function getInstance(): Clockwork
    {
        /** @var Clockwork */
        return self::getHelper()->getClockwork();
    }

    public static function getRequest(): Request
    {
        /** @var Request */
        return self::getInstance()->getRequest();
    }

    public static function getHelper(): VanillaClockwork
    {
        return self::$instance ??= self::init();
    }

    public static function getFullClockworkApiUrl(): string
    {
        $https = isset($_SERVER['HTTPS']) && 'on' == $_SERVER['HTTPS'];
        $host = Type::string($_SERVER['HTTP_HOST'] ?? null);
        $port = $_SERVER['SERVER_PORT'] ?? null;
        $uri = dirname(Type::string($_SERVER['REQUEST_URI'] ?? null)) . '/' . self::getClockworkApiUrl();

        $scheme = $https ? 'https' : 'http';
        $port = (!$https && 80 != $port || $https && 443 != $port) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$port}{$uri}";
    }

    public static function getClockworkApiUrl(): string
    {
        return Url::backendPage('debug', rex_api_debug::getUrlParams());
    }

    public static function ensureStoragePath(): void
    {
        $storagePath = self::getStoragePath();
        if (!is_dir($storagePath)) {
            Dir::create($storagePath);
        }
    }

    public static function getStoragePath(): string
    {
        return Addon::require('debug')->getCachePath('clockwork.db');
    }
}
