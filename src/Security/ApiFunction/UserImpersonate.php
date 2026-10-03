<?php

namespace Redaxo\Core\Security\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * @internal
 */
#[AsApiFunction('user_impersonate')]
final class UserImpersonate extends ApiFunction
{
    public function execute(): never
    {
        $impersonate = Request::get('_impersonate');

        if ('_depersonate' === $impersonate) {
            BackendLogin::requireCurrent()->depersonate();

            Response::sendRedirect(Url::backendPage('users/users'));
        }

        $user = Core::requireUser();
        if (!$user->admin) {
            throw new ApiFunctionException(escape(sprintf('Current user ("%s") must be admin to impersonate another user.', $user->login)));
        }

        BackendLogin::requireCurrent()->impersonate((int) $impersonate);

        Response::sendRedirect(Url::backendController());
    }
}
