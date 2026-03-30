<?php

/**
 * @package redaxo\core\login
 *
 * @internal
 */
class rex_api_has_user_session extends rex_api_function
{
    /** @return never */
    public function execute()
    {
        if (!rex_request::isHttps()) {
            throw new rex_api_exception('https is required');
        }

        $user = rex::getUser();
        if (!$user) {
            rex_response::sendJson(false);
            exit;
        }

        $perm = rex_get('perm');
        if ($perm) {
            rex_response::sendJson($user->hasPerm($perm));
            exit;
        }

        rex_response::sendJson(true);
        exit;
    }

    protected function requiresCsrfProtection()
    {
        // this action supports to be callable by 3rd party apps, which can't know our valid csrf token
        return false;
    }
}
