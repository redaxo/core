<?php

/**
 * Represents a null package.
 *
 * Instances of this class are returned by `rex_package::get()` for non-existing packages.
 * Thereby it is safe to call `rex_package::get(...)->isAvailable()` and `isInstalled()`.
 * Other methods should not be called on null-packages since they do not return useful values.
 * Some methods like `getPath()` throw exceptions.
 *
 * @author gharlan
 *
 * @package redaxo\core\packages
 */
abstract class rex_null_package implements rex_package_interface
{
    use rex_singleton_trait;

    public function getName()
    {
        return static::class;
    }

    /** @return rex_null_addon */
    public function getAddon()
    {
        return rex_null_addon::getInstance();
    }

    public function getPackageId()
    {
        return null;
    }

    public function getPath($file = '')
    {
        throw new rex_exception(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    public function getAssetsPath($file = '')
    {
        throw new rex_exception(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    public function getAssetsUrl($file = '')
    {
        throw new rex_exception(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    public function getDataPath($file = '')
    {
        throw new rex_exception(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    public function getCachePath($file = '')
    {
        throw new rex_exception(sprintf('Calling %s on %s is not allowed', __FUNCTION__, self::class));
    }

    public function setConfig($key, $value = null)
    {
        return false;
    }

    public function getConfig($key = null, $default = null)
    {
        return $default;
    }

    public function hasConfig($key = null)
    {
        return false;
    }

    public function removeConfig($key)
    {
        return false;
    }

    public function setProperty($key, $value) {}

    public function getProperty($key, $default = null)
    {
        return $default;
    }

    public function hasProperty($key)
    {
        return false;
    }

    public function removeProperty($key) {}

    public function isAvailable()
    {
        return false;
    }

    public function isInstalled()
    {
        return false;
    }

    public function isSystemPackage()
    {
        return false;
    }

    public function getAuthor($default = null)
    {
        return $default;
    }

    public function getVersion($format = null)
    {
        return '';
    }

    public function getSupportPage($default = null)
    {
        return $default;
    }

    public function includeFile($file, array $context = [])
    {
        return null;
    }

    public function i18n($key, ...$replacements)
    {
        $args = func_get_args();
        return call_user_func_array(rex_i18n::msg(...), $args);
    }
}
