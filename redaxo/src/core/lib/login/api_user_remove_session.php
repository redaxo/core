<?php

/**
 * @package redaxo\core\login
 * @internal
 */
class rex_api_user_remove_session extends rex_api_function
{
    public function execute()
    {
        $userId = rex_request::get('user_id', 'int');
        $user = rex::requireUser();

        if ($userId !== $user->getId() && !$user->isAdmin() && (!$user->hasPerm('users[]') || rex_user::require($userId)->isAdmin())) {
            throw new rex_api_exception('Permission denied');
        }

        $sessionHash = rex_request::get('session_hash', 'string');

        if (rex_user_session::getInstance()->removeSessionByHash($sessionHash, $userId)) {
            return new rex_api_result(true, rex_i18n::msg('session_removed'));
        }

        return new rex_api_result(false, rex_i18n::msg('session_remove_error'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
