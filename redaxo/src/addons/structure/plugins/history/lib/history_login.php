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
    public function checkTempSession($historyLogin, $historySession, $historyValidtime)
    {
        $userSql = rex_sql::factory($this->DB);
        $userSql->setQuery($this->loginQuery, [':login' => $historyLogin]);

        if (1 == $userSql->getRows()) {
            if (self::verifySessionKey($historyLogin . $userSql->getValue('session_id') . $historyValidtime, $historySession)) {
                $this->user = $userSql;
                $this->setSessionVar(rex_login::SESSION_LAST_ACTIVITY, time());
                $this->setSessionVar(rex_login::SESSION_USER_ID, $this->user->getValue($this->idColumn));
                $this->setSessionVar(rex_login::SESSION_PASSWORD, $this->user->getValue($this->passwordColumn));
                return parent::checkLogin();
            }
        }

        return false;
    }

    /** @return string|null */
    public static function createSessionKey(#[SensitiveParameter] $login, $session, $validtime)
    {
        return password_hash($login . $session . $validtime, PASSWORD_DEFAULT);
    }

    /** @return bool */
    public static function verifySessionKey($key1, $key2)
    {
        return password_verify($key1, $key2);
    }
}
