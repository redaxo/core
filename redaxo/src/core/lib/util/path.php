<?php

/**
 * Utility class to generate absolute paths.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_path
{
    /** @var rex_path_default_provider */
    protected static $pathprovider;

    /**
     * Initializes the class.
     *
     * @param rex_path_default_provider $pathprovider A path provider
     * @return void
     */
    public static function init($pathprovider)
    {
        self::$pathprovider = $pathprovider;
    }

    /**
     * Returns the base/root path.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function base($file = '')
    {
        return self::$pathprovider->base($file);
    }

    /**
     * Returns the path to the frontend (the document root).
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function frontend($file = '')
    {
        return self::$pathprovider->frontend($file);
    }

    /**
     * Returns the path to the frontend-controller (index.php from frontend).
     *
     * @return non-empty-string
     */
    public static function frontendController()
    {
        return self::$pathprovider->frontendController();
    }

    /**
     * Returns the path to the backend (folder where the backend controller is placed).
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function backend($file = '')
    {
        return self::$pathprovider->backend($file);
    }

    /**
     * Returns the path to the backend-controller (index.php from backend).
     *
     * @return non-empty-string
     */
    public static function backendController()
    {
        return self::$pathprovider->backendController();
    }

    /**
     * Returns the path to the media-folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function media($file = '')
    {
        return self::$pathprovider->media($file);
    }

    /**
     * Returns the path to the assets folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function assets($file = '')
    {
        return self::$pathprovider->assets($file);
    }

    /**
     * Returns the path to the assets folder of the core, which contains all assets required by the core to work properly.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function coreAssets($file = '')
    {
        return self::$pathprovider->coreAssets($file);
    }

    /**
     * Returns the path to the public assets folder of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param string $file File
     *
     * @return non-empty-string
     *
     * @see assets()
     */
    public static function addonAssets($addon, $file = '')
    {
        return self::$pathprovider->addonAssets($addon, $file);
    }

    /**
     * Returns the path to the public assets folder of the given plugin of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param non-empty-string $plugin Plugin
     * @param string $file File
     *
     * @return non-empty-string
     *
     * @see assets()
     */
    public static function pluginAssets($addon, $plugin, $file = '')
    {
        return self::$pathprovider->pluginAssets($addon, $plugin, $file);
    }

    /**
     * Returns the path to the bin folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function bin($file = '')
    {
        return self::$pathprovider->bin($file);
    }

    /**
     * Returns the path to the data folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function data($file = '')
    {
        return self::$pathprovider->data($file);
    }

    /**
     * Returns the path to the data folder of the core.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function coreData($file = '')
    {
        return self::$pathprovider->coreData($file);
    }

    /**
     * Returns the path to the data folder of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function addonData($addon, $file = '')
    {
        return self::$pathprovider->addonData($addon, $file);
    }

    /**
     * Returns the path to the data folder of the given plugin of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param non-empty-string $plugin Plugin
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function pluginData($addon, $plugin, $file = '')
    {
        return self::$pathprovider->pluginData($addon, $plugin, $file);
    }

    /**
     * Returns the path to the cache folder.
     *
     * @return non-empty-string
     */
    public static function log(string $file = ''): string
    {
        // BC
        if (!method_exists(self::$pathprovider, 'log')) {
            return self::data('log/' . $file);
        }

        return self::$pathprovider->log($file);
    }

    /**
     * Returns the path to the cache folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function cache($file = '')
    {
        return self::$pathprovider->cache($file);
    }

    /**
     * Returns the path to the cache folder of the core.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function coreCache($file = '')
    {
        return self::$pathprovider->coreCache($file);
    }

    /**
     * Returns the path to the cache folder of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function addonCache($addon, $file = '')
    {
        return self::$pathprovider->addonCache($addon, $file);
    }

    /**
     * Returns the path to the cache folder of the given plugin.
     *
     * @param non-empty-string $addon Addon
     * @param non-empty-string $plugin Plugin
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function pluginCache($addon, $plugin, $file = '')
    {
        return self::$pathprovider->pluginCache($addon, $plugin, $file);
    }

    /**
     * Returns the path to the src folder.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function src($file = '')
    {
        return self::$pathprovider->src($file);
    }

    /**
     * Returns the path to the actual core.
     *
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function core($file = '')
    {
        return self::$pathprovider->core($file);
    }

    /**
     * Returns the base path to the folder of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function addon($addon, $file = '')
    {
        return self::$pathprovider->addon($addon, $file);
    }

    /**
     * Returns the base path to the folder of the plugin of the given addon.
     *
     * @param non-empty-string $addon Addon
     * @param non-empty-string $plugin Plugin
     * @param string $file File
     *
     * @return non-empty-string
     */
    public static function plugin($addon, $plugin, $file = '')
    {
        return self::$pathprovider->plugin($addon, $plugin, $file);
    }

    /**
     * Converts a relative path to an absolute.
     *
     * @param non-empty-string $relPath The relative path
     *
     * @return string Absolute path
     */
    public static function absolute($relPath)
    {
        $stack = [];

        // pfadtrenner vereinheitlichen
        $relPath = str_replace('\\', '/', $relPath);
        foreach (explode('/', $relPath) as $dir) {
            // Aktuelles Verzeichnis, oder Ordner ohne Namen
            if ('.' == $dir || '' == $dir) {
                continue;
            }

            // Zum Parent
            if ('..' == $dir) {
                array_pop($stack);
            }
            // Normaler Ordner
            else {
                $stack[] = $dir;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $stack);
    }

    /**
     * Converts an absolute path to a relative one.
     *
     * If the path is outside of the base path, the absolute path will be kept.
     *
     * @param string $absPath
     * @param string|null $basePath Defaults to `rex_path::base()`
     *
     * @return string
     */
    public static function relative($absPath, $basePath = null)
    {
        if (null === $basePath) {
            $basePath = self::base();
        }

        $basePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $basePath);
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $baseLength = strlen($basePath);

        $absPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $absPath);

        if (substr($absPath, 0, $baseLength) !== $basePath) {
            return $absPath;
        }

        return substr($absPath, $baseLength);
    }

    /**
     * Returns the basename (filename) of the path independent of directory separator (/ or \).
     *
     * This method should be used to secure incoming GET/POST parameters containing a filename.
     *
     * @param string $path
     *
     * @return string
     */
    public static function basename($path)
    {
        /** @psalm-taint-escape text */
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        /** @psalm-suppress ForbiddenCode */
        return basename($path);
    }

    /** @return non-empty-string|null */
    public static function findBinaryPath(string $commandName): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $out = [];
        $cmd = sprintf('command -v %s || which %1$s', escapeshellarg($commandName));
        exec($cmd, $out, $ret);

        if (0 === $ret) {
            if ('' === $out[0]) {
                throw new rex_exception('empty binary path found.');
            }
            return (string) $out[0];
        }

        return null;
    }
}
