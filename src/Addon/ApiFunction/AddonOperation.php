<?php

namespace Redaxo\Core\Addon\ApiFunction;

use Override;
use Redaxo\Core\Addon\Addon as BaseAddon;
use Redaxo\Core\Addon\AddonManager;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Util\Type;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * @internal
 */
#[AsApiFunction('addon_operation')]
final class AddonOperation extends ApiFunction
{
    #[Override]
    public function execute(): Result
    {
        if (Core::isLiveMode()) {
            throw new ApiFunctionException('Package management is not available in live mode!');
        }

        $function = Request::request('function', 'string');
        if (!in_array($function, ['install', 'uninstall', 'activate', 'deactivate'])) {
            throw new ApiFunctionException('Unknown package function "' . escape($function) . '"!');
        }
        $packageId = Request::request('package', 'string');
        $package = BaseAddon::get($packageId);
        if ('uninstall' == $function && !$package->isInstalled()
            || 'activate' == $function && $package->isAvailable()
            || 'deactivate' == $function && !$package->isAvailable()
        ) {
            return new Result(true);
        }

        if (!$package instanceof BaseAddon) {
            throw new ApiFunctionException('Package "' . escape($packageId) . '" doesn\'t exists!');
        }
        $reinstall = 'install' === $function && $package->isInstalled();
        $manager = AddonManager::factory($package);
        $success = Type::bool($manager->$function());
        $message = $manager->getMessage();
        $result = new Result($success, $message);
        if ($success && !$reinstall) {
            $result->setRequiresReboot(true);
        }
        return $result;
    }
}
