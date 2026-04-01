<?php

/**
 * @package redaxo\core\packages
 *
 * @internal
 */
class rex_api_package extends rex_api_function
{
    public function execute()
    {
        if (rex::isLiveMode()) {
            throw new rex_api_exception('Package management is not available in live mode!');
        }

        $function = rex_request('function', 'string');
        if (!in_array($function, ['install', 'uninstall', 'activate', 'deactivate', 'delete'])) {
            throw new rex_api_exception('Unknown package function "' . rex_escape($function) . '"!');
        }
        $packageId = rex_request('package', 'string');
        $package = rex_package::get($packageId);
        if ('uninstall' == $function && !$package->isInstalled()
            || 'activate' == $function && $package->isAvailable()
            || 'deactivate' == $function && !$package->isAvailable()
            || 'delete' == $function && !rex_package::exists($packageId)
        ) {
            return new rex_api_result(true);
        }

        if (!$package instanceof rex_package) {
            throw new rex_api_exception('Package "' . rex_escape($packageId) . '" doesn\'t exists!');
        }
        $reinstall = 'install' === $function && $package->isInstalled();
        $manager = rex_package_manager::factory($package);
        $success = rex_type::bool($manager->$function());
        $message = $manager->getMessage();
        $result = new rex_api_result($success, $message);
        if ($success && !$reinstall) {
            $result->setRequiresReboot(true);
        }
        return $result;
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
