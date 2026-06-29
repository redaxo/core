<?php

/**
 * @package redaxo\install
 *
 * @internal
 */
class rex_api_install_package_update extends rex_api_function
{
    public function execute()
    {
        if (rex::isLiveMode()) {
            throw new rex_api_exception('Package management is not available in live mode!');
        }
        if (!rex::getUser()?->isAdmin()) {
            throw new rex_api_exception('You do not have the permission!');
        }
        $addonkey = rex_request('addonkey', 'string');
        $fileId = rex_request('file', 'int');

        $installer = new rex_install_package_update();

        try {
            $message = $installer->run($addonkey, $fileId);
        } catch (rex_functional_exception $exception) {
            throw new rex_api_exception($exception->getMessage());
        }

        if ($message) {
            $message = rex_i18n::msg('install_warning_addon_not_updated', $addonkey) . '<br />' . $message;
            $success = false;
        } else {
            $message = rex_i18n::msg('install_info_addon_updated', $addonkey);

            $success = true;
            unset($_REQUEST['addonkey']);
        }
        return new rex_api_result($success, $message);
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
