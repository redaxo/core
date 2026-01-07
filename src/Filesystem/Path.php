<?php

namespace Redaxo\Core\Filesystem;

use Redaxo\Core\Exception\LogicException;

use function function_exists;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;

/**
 * Utility class to generate absolute paths.
 */
final class Path
{
    private static DefaultPathProvider $pathprovider;

    private function __construct() {}

    /**
     * Initializes the class.
     */
    public static function init(DefaultPathProvider $pathProvider): void
    {
        self::$pathprovider = $pathProvider;
    }

    /**
     * Returns the base/root path.
     *
     * @return non-empty-string
     */
    public static function base(string $file = ''): string
    {
        return self::$pathprovider->base($file);
    }

    /**
     * Returns the path to the frontend (the document root).
     *
     * @return non-empty-string
     */
    public static function frontend(string $file = ''): string
    {
        return self::$pathprovider->frontend($file);
    }

    /**
     * Returns the path to the frontend-controller (index.php from frontend).
     *
     * @return non-empty-string
     */
    public static function frontendController(): string
    {
        return self::$pathprovider->frontendController();
    }

    /**
     * Returns the path to the backend (folder where the backend controller is placed).
     *
     * @return non-empty-string
     */
    public static function backend(string $file = ''): string
    {
        return self::$pathprovider->backend($file);
    }

    /**
     * Returns the path to the backend-controller (index.php from backend).
     *
     * @return non-empty-string
     */
    public static function backendController(): string
    {
        return self::$pathprovider->backendController();
    }

    /**
     * Returns the path to the media-folder.
     *
     * @return non-empty-string
     */
    public static function media(string $file = ''): string
    {
        return self::$pathprovider->media($file);
    }

    /**
     * Returns the path to the assets folder.
     *
     * @return non-empty-string
     */
    public static function assets(string $file = ''): string
    {
        return self::$pathprovider->assets($file);
    }

    /**
     * Returns the path to the assets folder of the core, which contains all assets required by the core to work properly.
     *
     * @return non-empty-string
     */
    public static function coreAssets(string $file = ''): string
    {
        return self::$pathprovider->coreAssets($file);
    }

    /**
     * Returns the path to the public assets folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public static function addonAssets(string $addon, string $file = ''): string
    {
        return self::$pathprovider->addonAssets($addon, $file);
    }

    /**
     * Returns the path to the bin folder.
     *
     * @return non-empty-string
     */
    public static function bin(string $file = ''): string
    {
        return self::$pathprovider->bin($file);
    }

    /**
     * Returns the path to the data folder.
     *
     * @return non-empty-string
     */
    public static function data(string $file = ''): string
    {
        return self::$pathprovider->data($file);
    }

    /**
     * Returns the path to the data folder of the core.
     *
     * @return non-empty-string
     */
    public static function coreData(string $file = ''): string
    {
        return self::$pathprovider->coreData($file);
    }

    /**
     * Returns the path to the data folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public static function addonData(string $addon, string $file = ''): string
    {
        return self::$pathprovider->addonData($addon, $file);
    }

    /**
     * Returns the path to the cache folder.
     *
     * @return non-empty-string
     */
    public static function log(string $file = ''): string
    {
        return self::$pathprovider->log($file);
    }

    /**
     * Returns the path to the cache folder.
     *
     * @return non-empty-string
     */
    public static function cache(string $file = ''): string
    {
        return self::$pathprovider->cache($file);
    }

    /**
     * Returns the path to the cache folder of the core.
     *
     * @return non-empty-string
     */
    public static function coreCache(string $file = ''): string
    {
        return self::$pathprovider->coreCache($file);
    }

    /**
     * Returns the path to the cache folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public static function addonCache(string $addon, string $file = ''): string
    {
        return self::$pathprovider->addonCache($addon, $file);
    }

    /**
     * Returns the path to the src folder.
     *
     * @return non-empty-string
     */
    public static function src(string $file = ''): string
    {
        return self::$pathprovider->src($file);
    }

    /**
     * Returns the path to the actual core.
     *
     * @return non-empty-string
     */
    public static function core(string $file = ''): string
    {
        return self::$pathprovider->core($file);
    }

    /**
     * Returns the base path to the folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public static function addon(string $addon, string $file = ''): string
    {
        return self::$pathprovider->addon($addon, $file);
    }

    /**
     * Converts a relative path to an absolute.
     *
     * @param non-empty-string $relPath The relative path
     * @return string Absolute path
     */
    public static function absolute(string $relPath): string
    {
        $stack = [];

        // pfadtrenner vereinheitlichen
        $relPath = str_replace('\\', '/', $relPath);
        foreach (explode('/', $relPath) as $dir) {
            // Aktuelles Verzeichnis, oder Ordner ohne Namen
            if ('.' == $dir || '' == $dir) {
                continue;
            }

            if ('..' == $dir) {
                array_pop($stack); // Zum Parent
            } else {
                $stack[] = $dir; // Normaler Ordner
            }
        }

        return implode(DIRECTORY_SEPARATOR, $stack);
    }

    /**
     * Converts an absolute path to a relative one.
     *
     * If the path is outside of the base path, the absolute path will be kept.
     *
     * @param string|null $basePath Defaults to `Path::base()`
     */
    public static function relative(string $absPath, ?string $basePath = null): string
    {
        if (null === $basePath) {
            $basePath = self::$pathprovider->base('');
            $coreBasePath = self::$pathprovider->coreBase('');
            if (str_starts_with($basePath, $coreBasePath)) {
                $basePath = $coreBasePath;
            }
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
     */
    public static function basename(string $path): string
    {
        /** @psalm-taint-escape text */
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        /** @psalm-suppress ForbiddenCode */
        return basename($path);
    }

    /**
     * @return non-empty-string|null
     */
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
                throw new LogicException('Empty binary path found.');
            }
            return (string) $out[0];
        }

        return null;
    }
}
