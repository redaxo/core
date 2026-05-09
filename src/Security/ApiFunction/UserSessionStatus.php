<?php

namespace Redaxo\Core\Security\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;

/**
 * @internal
 */
#[AsApiFunction('user_session_status')]
class UserSessionStatus extends ApiFunction
{
    // this action supports to be callable by 3rd party apps, which can't know our valid csrf token
    protected bool $requiresCsrfProtection = false;

    public function execute(): never
    {
        $user = Core::getUser();
        if (!$user) {
            Response::sendJson(false);
            exit;
        }

        $login = Core::getProperty('login');

        $restOverallTime = (int) Core::getProperty('session_max_overall_duration', 0) + (int) $login->getSessionVar(BackendLogin::SESSION_START_TIME) - (int) $login->getSessionVar(BackendLogin::SESSION_LAST_ACTIVITY);
        Response::sendJson([
            'rest_overall_time' => $restOverallTime,
        ]);

        exit;
    }
}
