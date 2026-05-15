<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Base\SingletonTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
final class UserSession
{
    use SingletonTrait;

    public const int STAY_LOGGED_IN_DURATION = 3; // months
    private const string SESSION_VAR_LAST_DB_UPDATE = 'last_db_update';

    public function storeCurrentSession(BackendLogin $login, ?string $cookieKey = null, ?string $passkey = null): void
    {
        $sessionId = session_id();
        if (false === $sessionId || '' === $sessionId) {
            return;
        }

        $userId = $login->getSessionVar(Login::SESSION_IMPERSONATOR, null);
        if (null === $userId) {
            $userId = $login->getSessionVar(Login::SESSION_USER_ID);
        }
        $userId = (int) $userId;

        $updateByCookieKey = false;
        if (null !== $cookieKey) {
            $sql = Sql::factory()
                ->setTable(Core::getTable('user_session'))
                ->setWhere(['cookie_key' => $cookieKey])
                ->select();
            if ($sql->getRows()) {
                if ($userId !== (int) $sql->getValue('user_id')) {
                    throw new RuntimeException('Cookie key "' . $cookieKey . '" does not belong to current user "' . $userId . '", it belongs to user "' . (string) $sql->getValue('user_id') . '"');
                }

                $updateByCookieKey = true;
            }
        }

        $sql = Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setValue('session_id', session_id())
            ->setValue('user_id', $userId)
            ->setValue('ip', Request::server('REMOTE_ADDR', 'string'))
            ->setValue('useragent', Request::server('HTTP_USER_AGENT', 'string'))
            ->setValue('last_activity', Sql::datetime($login->getSessionVar(Login::SESSION_LAST_ACTIVITY)))
        ;

        if ($updateByCookieKey) {
            $sql
                ->setWhere(['cookie_key' => $cookieKey])
                ->update();
        } else {
            if (null !== $cookieKey) {
                $sql->setValue('cookie_key', $cookieKey);
            }
            if (null !== $passkey) {
                $sql->setValue('passkey_id', $passkey);
            }

            $sql
                ->setValue('starttime', Sql::datetime($login->getSessionVar(Login::SESSION_START_TIME, time())))
                ->insertOrUpdate();
        }

        $login->setSessionVar(self::SESSION_VAR_LAST_DB_UPDATE, time());
    }

    public function clearCurrentSession(): void
    {
        $sessionId = session_id();
        if (false === $sessionId || '' === $sessionId) {
            return;
        }

        Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setWhere('session_id = ?', [session_id()])
            ->delete();
    }

    public function updateLastActivity(BackendLogin $login): void
    {
        $sessionId = session_id();
        if (false === $sessionId || '' === $sessionId) {
            return;
        }

        // only once a minute
        if ($login->getSessionVar(self::SESSION_VAR_LAST_DB_UPDATE, 0) > (time() - 60)) {
            return;
        }

        $this->storeCurrentSession($login);
    }

    public static function updateSessionId(string $previousId, string $newId): void
    {
        Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setWhere(['session_id' => $previousId])
            ->setValue('session_id', $newId)
            ->update();
    }

    public static function clearExpiredSessions(): void
    {
        Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setWhere('UNIX_TIMESTAMP(last_activity) < IF(cookie_key IS NULL, ?, ?)', [
                time() - (int) Core::getProperty('session_duration'),
                strtotime('-' . self::STAY_LOGGED_IN_DURATION . ' months'),
            ])
            ->delete();
    }

    public function removeSession(string $sessionId, int $userId): bool
    {
        $sql = Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setWhere('session_id = ? and user_id = ?', [$sessionId, $userId])
            ->delete();

        return $sql->getRows() > 0;
    }

    public function removeSessionsExceptCurrent(int $userId): void
    {
        $sessionId = session_id();
        if (false === $sessionId || '' === $sessionId) {
            return;
        }

        Sql::factory()
            ->setTable(Core::getTable('user_session'))
            ->setWhere('session_id != ? and user_id = ?', [$sessionId, $userId])
            ->delete();
    }
}
