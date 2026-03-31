<?php

use Composer\CaBundle\CaBundle;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\HttpClient\Exception\HttpClientException;
use Redaxo\Core\HttpClient\Request;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
class rex_install_webservice
{
    public const HOST = 'redaxo.org';
    public const PORT = 443;
    public const SSL = true;
    public const PATH = '/de/ws/';
    public const REFRESH_CACHE = 600;

    /** @var array<string, array{stamp: int, data: array}> */
    private static $cache;

    /**
     * Retrieves the json-decoded content of the given path.
     *
     * @param string $path path to local cache-file
     *
     * @throws UserMessageException
     *
     * @return array
     */
    public static function getJson($path)
    {
        if (is_array($cache = self::getCache($path))) {
            return $cache;
        }
        $fullpath = self::PATH . self::getPath($path);

        $error = null;
        try {
            $socket = Request::factory(self::HOST, self::PORT, self::SSL);
            $socket->setPath($fullpath);
            self::configureSecureSsl($socket);
            $response = $socket->doGet();
            if ($response->isOk()) {
                $data = json_decode($response->getBody(), true);
                if (isset($data['error']) && is_string($data['error'])) {
                    $error = I18n::msg('install_webservice_error') . '<br />' . $data['error'];
                } elseif (is_array($data)) {
                    self::setCache($path, $data);
                    return $data;
                }
            }
        } catch (HttpClientException $e) {
            Logger::logException($e);
        }

        if (!$error) {
            $error = I18n::msg('install_webservice_unreachable');
        }

        throw new UserMessageException($error);
    }

    /**
     * Download the content of the given url and make it available as a local file.
     *
     * @param string $url Url to a resource to download
     *
     * @throws UserMessageException
     *
     * @return string Returns a local path to the downloaded archive
     */
    public static function getArchive($url)
    {
        try {
            $socket = Request::factoryUrl($url);
            // Only apply secure SSL config for redaxo.org URLs
            if (self::HOST === parse_url($url, PHP_URL_HOST)) {
                self::configureSecureSsl($socket);
            }
            $response = $socket->doGet();
            if ($response->isOk()) {
                $filename = Path::basename($url);
                $file = Path::addonCache('install', rtrim(md5($filename) . '.' . File::extension($filename), '.'));
                $response->writeBodyTo($file);
                return $file;
            }
        } catch (HttpClientException $e) {
            Logger::logException($e);
        }

        throw new UserMessageException(I18n::msg('install_archive_unreachable'));
    }

    /**
     * POSTs the given data to the redaxo.org webservice.
     *
     * @param string $path
     * @param string|null $archive Path to archive
     * @throws UserMessageException
     * @return void
     */
    public static function post($path, array $data, $archive = null)
    {
        $fullpath = self::PATH . self::getPath($path);
        $error = null;
        try {
            $socket = Request::factory(self::HOST, self::PORT, self::SSL);
            $socket->setPath($fullpath);
            self::configureSecureSsl($socket);
            $files = [];
            if ($archive) {
                $files['archive']['path'] = $archive;
                $files['archive']['type'] = 'application/zip';
            }
            $response = $socket->doPost($data, $files);
            if ($response->isOk()) {
                $data = json_decode($response->getBody(), true);
                if (!isset($data['error']) || !is_string($data['error'])) {
                    return;
                }
                $error = I18n::msg('install_webservice_error') . '<br />' . $data['error'];
            }
        } catch (HttpClientException $e) {
            Logger::logException($e);
        }

        if (!$error) {
            $error = I18n::msg('install_webservice_unreachable');
        }

        throw new UserMessageException($error);
    }

    /**
     * Issues a http DELETE to the given path.
     *
     * @param string $path
     * @throws UserMessageException
     * @return void
     */
    public static function delete($path)
    {
        $fullpath = self::PATH . self::getPath($path);
        $error = null;
        try {
            $socket = Request::factory(self::HOST, self::PORT, self::SSL);
            $socket->setPath($fullpath);
            self::configureSecureSsl($socket);
            $response = $socket->doDelete();
            if ($response->isOk()) {
                $data = json_decode($response->getBody(), true);
                if (!isset($data['error']) || !is_string($data['error'])) {
                    return;
                }
                $error = I18n::msg('install_webservice_error') . '<br />' . $data['error'];
            }
        } catch (HttpClientException $e) {
            Logger::logException($e);
        }

        if (!$error) {
            $error = I18n::msg('install_webservice_unreachable');
        }

        throw new UserMessageException($error);
    }

    /**
     * Appends api login credentials to the given path.
     *
     * @param string $path
     *
     * @return string
     */
    private static function getPath($path)
    {
        $path = !str_contains($path, '?') ? rtrim($path, '/') . '/?' : $path . '&';
        $path .= 'rex_version=' . Core::getVersion();

        /** @var array<string, string>|null $config */
        static $config;
        if (null === $config) {
            /** @var array<string, string> $config */
            $config = File::getCache(Path::addonData('install', 'config.json'));
        }

        if (isset($config['api_login']) && $config['api_login'] && isset($config['api_key'])) {
            $path .= '&api_login=' . urlencode($config['api_login']) . '&api_key=' . urlencode($config['api_key']);
        }

        return $path;
    }

    /**
     * Deletes the local webservice cache.
     *
     * @param string|null $pathBegin
     * @return void
     */
    public static function deleteCache($pathBegin = null)
    {
        self::loadCache();
        if ($pathBegin) {
            foreach (self::$cache as $path => $_) {
                if (str_starts_with($path, $pathBegin)) {
                    unset(self::$cache[$path]);
                }
            }
        } else {
            self::$cache = [];
        }
        File::putCache(Path::addonCache('install', 'webservice.cache'), self::$cache);
    }

    /**
     * Returns the content for the given path out of the local cache.
     *
     * @param string $path
     *
     * @return array|null
     */
    private static function getCache($path)
    {
        self::loadCache();
        if (isset(self::$cache[$path])) {
            return self::$cache[$path]['data'];
        }
        return null;
    }

    /**
     * Loads the local cached data into memory (only fresh data will be loaded).
     * @return void
     */
    private static function loadCache()
    {
        if (null === self::$cache) {
            /** @var array<string, array{stamp: int, data: array}> $cache */
            $cache = (array) File::getCache(Path::addonCache('install', 'webservice.cache'));
            foreach ($cache as $path => $pathCache) {
                if ($pathCache['stamp'] > time() - self::REFRESH_CACHE) {
                    self::$cache[$path] = $pathCache;
                }
            }
        }
    }

    /** Configures secure SSL options for redaxo.org connections. */
    private static function configureSecureSsl(Request $socket): void
    {
        $socket->setOptions(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'verify_depth' => 3,
            'disable_compression' => true,
            'SNI_enabled' => true,
            // Enforce TLS 1.2+ only, prevent fallback to older protocols
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            // TLS 1.3 cipher suites (preferred)
            'ciphersuites' => 'TLS_AES_256_GCM_SHA384:TLS_AES_128_GCM_SHA256:TLS_CHACHA20_POLY1305_SHA256',
            // TLS 1.2 cipher suites (fallback)
            'ciphers' => 'ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA256',
            // Set CA bundle for enhanced security
            'cafile' => CaBundle::getBundledCaBundlePath(),
        ]]);
    }

    /**
     * Writes the given data into the local cache.
     *
     * @param string $path
     * @param array $data
     * @return void
     */
    private static function setCache($path, $data)
    {
        self::$cache[$path]['stamp'] = time();
        self::$cache[$path]['data'] = $data;
        File::putCache(Path::addonCache('install', 'webservice.cache'), self::$cache);
    }
}
