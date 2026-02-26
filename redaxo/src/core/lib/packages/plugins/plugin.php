<?php

/**
 * Class for plugins.
 *
 * @author gharlan
 *
 * @package redaxo\core\packages
 */
class rex_plugin extends rex_package implements rex_plugin_interface
{
    /**
     * Parent addon.
     *
     * @var rex_addon
     */
    private $addon;

    /**
     * @param string $name Name
     * @param rex_addon $addon Parent addon
     */
    public function __construct($name, rex_addon $addon)
    {
        parent::__construct($name);
        $this->addon = $addon;
    }

    /**
     * Returns the plugin by the given name.
     *
     * @param string $addon Name of the addon
     * @param string $plugin Name of the plugin
     *
     * @throws InvalidArgumentException
     *
     * @return rex_plugin_interface If the plugin exists, a `rex_plugin` is returned, otherwise a `rex_null_plugin`
     *
     * @psalm-suppress ParamNameMismatch
     */
    public static function get($addon, $plugin = null)
    {
        if (null === $plugin) {
            throw new InvalidArgumentException('Missing Argument 2 for ' . __METHOD__ . '()');
        }
        if (!is_string($addon)) {
            throw new InvalidArgumentException('Expecting $addon to be string, but ' . gettype($addon) . ' given!');
        }
        if (!is_string($plugin)) {
            throw new InvalidArgumentException('Expecting $plugin to be string, but ' . gettype($plugin) . ' given!');
        }
        return rex_addon::get($addon)->getPlugin($plugin);
    }

    /**
     * Returns the plugin by the given name.
     *
     * @throws RuntimeException if the plugin does not exist
     *
     * @return self
     *
     * @psalm-suppress ParamNameMismatch
     */
    public static function require(string $addon, ?string $plugin = null): rex_package
    {
        if (null === $plugin) {
            throw new InvalidArgumentException('Missing Argument 2 for ' . __METHOD__ . '()');
        }

        return rex_addon::require($addon)->requirePlugin($plugin);
    }

    /**
     * Returns if the plugin exists.
     *
     * @param string $addon Name of the addon
     * @param string $plugin Name of the plugin
     *
     * @return bool
     *
     * @psalm-suppress ParamNameMismatch
     */
    public static function exists($addon, $plugin = null)
    {
        return rex_addon::exists($addon) && rex_addon::get($addon)->pluginExists($plugin);
    }

    /** @return rex_addon */
    public function getAddon()
    {
        return $this->addon;
    }

    /** @return string */
    public function getPackageId()
    {
        return $this->getAddon()->getName() . '/' . $this->getName();
    }

    public function getType()
    {
        return 'plugin';
    }

    public function getPath($file = '')
    {
        return rex_path::plugin($this->getAddon()->getName(), $this->getName(), $file);
    }

    public function getAssetsPath($file = '')
    {
        return rex_path::pluginAssets($this->getAddon()->getName(), $this->getName(), $file);
    }

    public function getAssetsUrl($file = '')
    {
        return rex_url::pluginAssets($this->getAddon()->getName(), $this->getName(), $file);
    }

    public function getDataPath($file = '')
    {
        return rex_path::pluginData($this->getAddon()->getName(), $this->getName(), $file);
    }

    public function getCachePath($file = '')
    {
        return rex_path::pluginCache($this->getAddon()->getName(), $this->getName(), $file);
    }

    public function isAvailable()
    {
        return $this->getAddon()->isAvailable() && parent::isAvailable();
    }

    public function isSystemPackage()
    {
        return in_array($this->getName(), (array) $this->addon->getProperty('system_plugins', []));
    }

    public function i18n($key, ...$replacements)
    {
        $args = func_get_args();
        $key = $this->getAddon()->getName() . '_' . $this->getName() . '_' . $key;
        if (rex_i18n::hasMsgOrFallback($key)) {
            $args[0] = $key;
            return call_user_func_array(rex_i18n::msg(...), $args);
        }
        return call_user_func_array([$this->getAddon(), 'i18n'], $args);
    }

    /**
     * Returns the registered plugins of the given addon.
     *
     * @param string $addon Addon name
     *
     * @return array<string, self>
     */
    public static function getRegisteredPlugins($addon)
    {
        return rex_addon::get($addon)->getRegisteredPlugins();
    }

    /**
     * Returns the installed plugins of the given addons.
     *
     * @param string $addon Addon name
     *
     * @return array<string, self>
     */
    public static function getInstalledPlugins($addon)
    {
        return rex_addon::get($addon)->getInstalledPlugins();
    }

    /**
     * Returns the available plugins of the given addons.
     *
     * @param string $addon Addon name
     *
     * @return array<string, self>
     */
    public static function getAvailablePlugins($addon)
    {
        return rex_addon::get($addon)->getAvailablePlugins();
    }

    /**
     * Returns the system plugins of the given addons.
     *
     * @param string $addon Addon name
     *
     * @return array<string, self>
     */
    public static function getSystemPlugins($addon)
    {
        return rex_addon::get($addon)->getSystemPlugins();
    }
}
