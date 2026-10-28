<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\Url;

use function in_array;
use function sprintf;

final class Asset
{
    public const JS_DEFERED = 'defer';
    public const JS_ASYNC = 'async';
    public const JS_IMMUTABLE = 'immutable';
    /** Loads the file as an ES module (with `JS_IMMUTABLE` its URL is rewritten, so it can't import siblings relatively). */
    public const JS_MODULE = 'module';

    /** @var array<string, list<string>> */
    private static array $cssFiles = [];
    /** @var list<array{string, array<self::JS_*, bool>}> */
    private static array $jsFiles = [];
    /** @var array<string, mixed> */
    private static array $jsProperties = [];

    private static ?string $favicon = null;

    /** Whether the CSS files have been read for the head of the page, later added files would be missing. */
    private static bool $cssFilesRead = false;

    private function __construct() {}

    /**
     * Loads the styles of the Bootstrap 3 markup (`.btn`, `.form-control`, `.table` …), for addons that still output
     * it; the core itself uses its own components. Can be called several times, but only before the head of the page
     * is output, e.g. in the `boot.php` of the addon (a page or fragment is too late).
     *
     * @throws LogicException if the head of the page has already been output
     *
     * @deprecated since 6.0, move the markup to the REDAXO components; the styles go away with REDAXO 7
     */
    public static function requireBootstrapCompat(): void
    {
        $file = Url::coreAssets('css/bootstrap-compat.css');
        if (in_array($file, self::$cssFiles['all'] ?? [], true)) {
            return;
        }
        if (self::$cssFilesRead) {
            throw new LogicException('Asset::requireBootstrapCompat() must be called before the head of the page is output, e.g. in the boot.php of the addon.');
        }
        self::$cssFiles['all'][] = $file;
    }

    /** Adds a CSS file. */
    public static function addCssFile(string $file, string $media = 'all'): void
    {
        if (isset(self::$cssFiles[$media]) && in_array($file, self::$cssFiles[$media])) {
            throw new RuntimeException(sprintf('The CSS file "%s" is already added to media "%s".', $file, $media));
        }

        self::$cssFiles[$media][] = $file;
    }

    /**
     * Returns the CSS files.
     *
     * @return array<string, list<string>>
     */
    public static function getCssFiles(): array
    {
        self::$cssFilesRead = true;

        return self::$cssFiles;
    }

    /**
     * Adds a JS file.
     *
     * @param array<self::JS_*, bool> $options
     */
    public static function addJsFile(string $file, array $options = []): void
    {
        if (empty($options)) {
            $options[self::JS_IMMUTABLE] = false;
        }

        if (in_array($file, self::$jsFiles)) {
            throw new RuntimeException(sprintf('The JS file "%s" is already added.', $file));
        }

        self::$jsFiles[] = [$file, $options];
    }

    /**
     * Returns the JS files.
     *
     * @return list<string>
     */
    public static function getJsFiles(): array
    {
        // transform for BC
        return array_map(static function ($jsFile) {
            return $jsFile[0];
        }, self::$jsFiles);
    }

    /**
     * Returns all JS files besides their options.
     *
     * @return list<array{string, array<self::JS_*, bool>}>
     */
    public static function getJsFilesWithOptions(): array
    {
        return self::$jsFiles;
    }

    /** Sets a JS property. */
    public static function setJsProperty(string $key, mixed $value): void
    {
        self::$jsProperties[$key] = $value;
    }

    /**
     * Returns the JS properties.
     *
     * @return array<string, mixed>
     */
    public static function getJsProperties(): array
    {
        return self::$jsProperties;
    }

    /** Sets the favicon path. */
    public static function setFavicon(?string $file): void
    {
        self::$favicon = $file;
    }

    /** Returns the favicon. */
    public static function getFavicon(): ?string
    {
        return self::$favicon;
    }
}
