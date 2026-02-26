<?php

/**
 * @extends rex_package_manager<rex_plugin>
 *
 * @package redaxo\core\packages
 */
class rex_plugin_manager extends rex_package_manager
{
    /** @param rex_plugin $plugin Plugin */
    protected function __construct(rex_plugin $plugin)
    {
        parent::__construct($plugin, 'plugin_');
    }

    protected function wrongPackageId($addonName, $pluginName = null)
    {
        if (null === $pluginName) {
            return $this->i18n('is_addon', $addonName);
        }
        if ($addonName != $this->package->getAddon()->getName()) {
            return $this->i18n('is_plugin', $addonName, $pluginName);
        }
        return $this->i18n('wrong_dir_name', $pluginName);
    }
}
