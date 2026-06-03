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

    /** @var array<string, list<string>> */
    private static array $cssFiles = [];
    /** @var list<array{string, array}> */
    private static array $jsFiles = [];
    /** @var array<string, mixed> */
    private static array $jsProperties = [];

    private static ?string $favicon = null;

    private function __construct() {}

    /**
     * Adds a CSS file.
     *
     * @param string $file
     * @param string $media
     * @return void
     */
    public static function addCssFile($file, $media = 'all')
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
    public static function getCssFiles()
    {
        return self::$cssFiles;
    }

    /**
     * Adds a JS file.
     *
     * @param string $file
     * @param array<self::JS_*, bool>|array<self::JS_*> $options
     * @return void
     */
    public static function addJsFile($file, array $options = [])
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
    public static function getJsFiles()
    {
        // transform for BC
        return array_map(static function ($jsFile) {
            return $jsFile[0];
        }, self::$jsFiles);
    }

    /**
     * Returns all JS files besides their options.
     *
     * @return list<array{string, array}>
     */
    public static function getJsFilesWithOptions()
    {
        return self::$jsFiles;
    }

    /**
     * Sets a JS property.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setJsProperty($key, $value)
    {
        self::$jsProperties[$key] = $value;
    }

    /**
     * Returns the JS properties.
     *
     * @return array<string, mixed>
     */
    public static function getJsProperties()
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
