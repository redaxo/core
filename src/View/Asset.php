<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Exception\RuntimeException;

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
    /** @var array<string, string> */
    private static array $cssLayers = [];
    /** @var list<array{string, array<self::JS_*, bool>}> */
    private static array $jsFiles = [];
    /** @var array<string, mixed> */
    private static array $jsProperties = [];

    private static ?string $favicon = null;

    private function __construct() {}

    /**
     * Adds a CSS file.
     *
     * With a `$layer` the file is imported into that cascade layer, e.g. to let other styles override it regardless
     * of specificity.
     */
    public static function addCssFile(string $file, string $media = 'all', ?string $layer = null): void
    {
        if (isset(self::$cssFiles[$media]) && in_array($file, self::$cssFiles[$media])) {
            throw new RuntimeException(sprintf('The CSS file "%s" is already added to media "%s".', $file, $media));
        }

        self::$cssFiles[$media][] = $file;
        if (null !== $layer) {
            self::$cssLayers[$file] = $layer;
        }
    }

    /** Returns the cascade layer a CSS file is imported into, if any. */
    public static function getCssLayer(string $file): ?string
    {
        return self::$cssLayers[$file] ?? null;
    }

    /**
     * Returns the CSS files.
     *
     * @return array<string, list<string>>
     */
    public static function getCssFiles(): array
    {
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
