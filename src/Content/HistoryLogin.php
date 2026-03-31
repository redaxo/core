<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\Login;
use SensitiveParameter;

use const PASSWORD_DEFAULT;

/**
 * @internal
 */
class HistoryLogin extends BackendLogin
{
    /** @return bool */
    public function checkTempSession($historyLogin, $historySession, $historyValidtime)
    {
        $userSql = Sql::factory($this->DB);
        $userSql->setQuery($this->loginQuery, [':login' => $historyLogin]);

        if (1 == $userSql->getRows()) {
            if (self::verifySessionKey($historyLogin . $userSql->getValue('session_id') . $historyValidtime, $historySession)) {
                $this->user = $userSql;
                $this->setSessionVar(Login::SESSION_LAST_ACTIVITY, time());
                $this->setSessionVar(Login::SESSION_USER_ID, $this->user->getValue($this->idColumn));
                $this->setSessionVar(Login::SESSION_PASSWORD, $this->user->getValue($this->passwordColumn));
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
