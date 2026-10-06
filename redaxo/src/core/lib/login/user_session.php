<?php

/**
 * @package redaxo\core\login
 *
 * @internal
 */
class rex_user_session
{
    use rex_singleton_trait;

    public const STAY_LOGGED_IN_DURATION = 3; // months
    private const SESSION_VAR_LAST_DB_UPDATE = 'last_db_update';

    public function storeCurrentSession(rex_backend_login $login, ?string $cookieKey = null, ?string $passkey = null): void
    {
        $sessionId = session_id();
        if (false === $sessionId || '' === $sessionId) {
            return;
        }

        $userId = $login->getSessionVar(rex_login::SESSION_IMPERSONATOR, null);
        if (null === $userId) {
            $userId = $login->getSessionVar(rex_login::SESSION_USER_ID);
        }
        $userId = (int) $userId;

        $updateByCookieKey = false;
        if (null !== $cookieKey) {
            $sql = rex_sql::factory()
                ->setTable(rex::getTable('user_session'))
                ->setWhere(['cookie_key' => $cookieKey])
                ->select();
            if ($sql->getRows()) {
                if ($userId !== (int) $sql->getValue('user_id')) {
                    throw new rex_exception('Cookie key "' . $cookieKey . '" does not belong to current user "' . $userId . '", it belongs to user "' . (string) $sql->getValue('user_id') . '"');
                }

                $updateByCookieKey = true;
            }
        }

        $sql = rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setValue('session_id', session_id())
            ->setValue('user_id', $userId)
            ->setValue('ip', rex_request::server('REMOTE_ADDR', 'string'))
            ->setValue('useragent', rex_request::server('HTTP_USER_AGENT', 'string'))
            ->setValue('last_activity', rex_sql::datetime($login->getSessionVar(rex_login::SESSION_LAST_ACTIVITY)))
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
                ->setValue('starttime', rex_sql::datetime($login->getSessionVar(rex_login::SESSION_START_TIME, time())))
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

        rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setWhere('session_id = ?', [session_id()])
            ->delete();
    }

    public function updateLastActivity(rex_backend_login $login): void
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
        rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setWhere(['session_id' => $previousId])
            ->setValue('session_id', $newId)
            ->update();
    }

    public static function updateCookieKey(string $sessionId, string $cookieKey): void
    {
        rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setWhere(['session_id' => $sessionId])
            ->setValue('cookie_key', $cookieKey)
            ->update();
    }

    public static function clearExpiredSessions(): void
    {
        rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setWhere('UNIX_TIMESTAMP(last_activity) < IF(cookie_key IS NULL, ?, ?)', [
                time() - (int) rex::getProperty('session_duration'),
                strtotime('-' . self::STAY_LOGGED_IN_DURATION . ' months'),
            ])
            ->delete();
    }

    public function removeSession(string $sessionId, int $userId): bool
    {
        $sql = rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
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

        rex_sql::factory()
            ->setTable(rex::getTable('user_session'))
            ->setWhere('session_id != ? and user_id = ?', [$sessionId, $userId])
            ->delete();
    }
}
