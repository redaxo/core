<?php

/**
 * @author dergel
 *
 * @package redaxo\structure\history
 *
 * @internal
 */
class rex_history_login extends rex_backend_login
{
    /** @return bool */
    public function checkTempSession(string $historyLogin, string $historySession, string $historyValidtime)
    {
        $userSql = rex_sql::factory($this->DB);
        $userSql->setQuery($this->loginQuery, [':login' => $historyLogin]);

        if (1 != $userSql->getRows()) {
            return false;
        }

        // Only non-expired sessions count (same rule as clearExpiredSessions, which runs on login only,
        // so expired rows can linger). Otherwise a known but expired session id could still forge a token.
        $sessionSql = rex_sql::factory($this->DB);
        $sessionSql->setQuery(
            'SELECT session_id FROM ' . rex::getTable('user_session') . '
                WHERE user_id = ? AND UNIX_TIMESTAMP(last_activity) >= IF(cookie_key IS NULL, ?, ?)',
            [
                (int) $userSql->getValue($this->idColumn),
                time() - (int) rex::getProperty('session_duration'),
                strtotime('-' . rex_user_session::STAY_LOGGED_IN_DURATION . ' months'),
            ],
        );

        // The session id is the shared secret — only its HMAC is in the URL. A matching row also proves
        // the session is still alive: logout removes it and thereby invalidates the token.
        foreach ($sessionSql as $session) {
            $expected = self::hashSessionKey($historyLogin, $historyValidtime, (string) $session->getValue('session_id'));

            if (hash_equals($expected, $historySession)) {
                $this->user = $userSql;
                $this->setSessionVar(rex_login::SESSION_LAST_ACTIVITY, time());
                $this->setSessionVar(rex_login::SESSION_USER_ID, $this->user->getValue($this->idColumn));
                $this->setSessionVar(rex_login::SESSION_PASSWORD, $this->user->getValue($this->passwordColumn));
                return parent::checkLogin();
            }
        }

        return false;
    }

    /** @return string */
    public static function createSessionKey(string $login, string $validtime)
    {
        return self::hashSessionKey($login, $validtime, (string) session_id());
    }

    private static function hashSessionKey(string $login, string $validtime, #[SensitiveParameter] string $sessionId): string
    {
        return hash_hmac('sha256', $login . '|' . $validtime, $sessionId);
    }
}
