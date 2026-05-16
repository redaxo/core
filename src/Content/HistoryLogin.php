<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\Login;
use SensitiveParameter;

use const PASSWORD_DEFAULT;

/**
 * @internal
 * @psalm-suppress InvalidExtendClass
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
final class HistoryLogin extends BackendLogin
{
    public function checkTempSession(string $historyLogin, #[SensitiveParameter] string $historySession, string $historyValidtime): bool
    {
        $userSql = Sql::factory($this->DB);
        $userSql->setQuery($this->loginQuery, [':login' => $historyLogin]);

        if (1 == $userSql->getRows()) {
            if (self::verifySessionKey($historyLogin . ((string) $userSql->getValue('session_id')) . $historyValidtime, $historySession)) {
                $this->user = $userSql;
                $this->setSessionVar(Login::SESSION_LAST_ACTIVITY, time());
                $this->setSessionVar(Login::SESSION_USER_ID, $this->user->getValue($this->idColumn));
                $this->setSessionVar(Login::SESSION_PASSWORD, $this->user->getValue($this->passwordColumn));
                return parent::checkLogin();
            }
        }

        return false;
    }

    public static function createSessionKey(#[SensitiveParameter] string $login, #[SensitiveParameter] string $session, string $validtime): string
    {
        return password_hash($login . $session . $validtime, PASSWORD_DEFAULT);
    }

    private static function verifySessionKey(#[SensitiveParameter] string $key1, #[SensitiveParameter] string $key2): bool
    {
        return password_verify($key1, $key2);
    }
}
