<?php

/**
 * Represents a null addon.
 *
 * @author gharlan
 *
 * @package redaxo\core\packages
 */
class rex_null_addon extends rex_null_package implements rex_addon_interface
{
    public function getType()
    {
        return 'addon';
    }

    /** @return rex_null_plugin */
    public function getPlugin($plugin)
    {
        return rex_null_plugin::getInstance();
    }

    public function pluginExists($plugin)
    {
        return false;
    }

    public function getRegisteredPlugins()
    {
        return [];
    }

    public function getInstalledPlugins()
    {
        return [];
    }

    public function getAvailablePlugins()
    {
        return [];
    }

    public function getSystemPlugins()
    {
        return [];
    }
}
