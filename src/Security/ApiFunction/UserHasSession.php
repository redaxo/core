<?php

namespace Redaxo\Core\Security\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;

/**
 * @internal
 */
#[AsApiFunction('user_has_session')]
final class UserHasSession extends ApiFunction
{
    // this action supports to be callable by 3rd party apps, which can't know our valid csrf token
    protected bool $requiresCsrfProtection = false;

    public function execute(): never
    {
        if (!Request::isHttps()) {
            throw new ApiFunctionException('https is required');
        }

        $user = Core::getUser();
        if (!$user) {
            Response::sendJson(false);
            exit;
        }

        $perm = Request::get('perm');
        if ($perm) {
            Response::sendJson($user->hasPerm($perm));
            exit;
        }

        Response::sendJson(true);
        exit;
    }
}
