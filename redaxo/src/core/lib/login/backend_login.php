<?php

/**
 * @package redaxo\core\login
 *
 * @method rex_user|null getUser()
 * @method rex_user|null getImpersonator()
 */
class rex_backend_login extends rex_login
{
    public const SYSTEM_ID = 'backend_login';
    public const SESSION_STAY_LOGGED_IN = 'stay_logged_in';

    private const SESSION_PASSWORD_CHANGE_REQUIRED = 'password_change_required';

    private string $tableName;
    private ?string $passkey = null;
    private bool $stayLoggedIn = false;
    private rex_backend_password_policy $passwordPolicy;

    public function __construct()
    {
        parent::__construct();

        $tableName = rex::getTablePrefix() . 'user';
        $this->setSqlDb(1);
        $this->setSystemId(self::SYSTEM_ID);
        $this->setSessionDuration(rex::getProperty('session_duration'));
        $qry = 'SELECT * FROM ' . $tableName;
        $this->setUserQuery($qry . ' WHERE id = :id AND status = 1');
        $this->setImpersonateQuery($qry . ' WHERE id = :id');
        $this->passwordPolicy = rex_backend_password_policy::factory();

        $loginPolicy = $this->getLoginPolicy();

        // XXX because with concat the time into the sql query, users of this class should use checkLogin() immediately after creating the object.
        $qry .= ' WHERE
            status = 1
            AND login = :login
            AND login_tries < ' . $loginPolicy->getMaxTriesUntilBlock() . '
            AND (
                login_tries < ' . $loginPolicy->getMaxTriesUntilDelay() . '
                OR
                login_tries >= ' . $loginPolicy->getMaxTriesUntilDelay() . ' AND lasttrydate < "' . rex_sql::datetime(time() - $loginPolicy->getReloginDelay()) . '"
            )';

        if ($blockAccountAfter = $this->passwordPolicy->getBlockAccountAfter()) {
            $datetime = (new DateTimeImmutable())->sub($blockAccountAfter);
            $qry .= ' AND password_changed > "' . $datetime->format(rex_sql::FORMAT_DATETIME) . '"';
        }

        $this->setLoginQuery($qry);

        $this->tableName = $tableName;
    }

    public function setPasskey(?string $data): void
    {
        $this->passkey = $data;
    }

    /**
     * @param bool $stayLoggedIn
     * @return void
     */
    public function setStayLoggedIn($stayLoggedIn = false)
    {
        if (!$this->getLoginPolicy()->isStayLoggedInEnabled()) {
            $stayLoggedIn = false;
        }

        $this->stayLoggedIn = (bool) $stayLoggedIn;
    }

    public function checkLogin()
    {
        $sql = rex_sql::factory();
        $userId = $this->getSessionVar(rex_login::SESSION_USER_ID);
        $cookiename = self::getStayLoggedInCookieName();
        $loggedInViaCookie = false;

        if ($cookiekey = rex_cookie($cookiename, 'string', null)) {
            if (!$userId) {
                $sql->setQuery('
                    SELECT id, password
                    FROM ' . rex::getTable('user') . ' user
                    JOIN ' . rex::getTable('user_session') . ' ON user.id = user_id
                    WHERE cookie_key = ?
                    LIMIT 1
                ', [$cookiekey]);
                if (1 == $sql->getRows()) {
                    $this->setSessionVar(rex_login::SESSION_USER_ID, $sql->getValue('id'));
                    $this->setSessionVar(rex_login::SESSION_PASSWORD, $sql->getValue('password'));
                    self::setStayLoggedInCookie($cookiekey);
                    $loggedInViaCookie = true;
                } else {
                    self::deleteStayLoggedInCookie();
                    $cookiekey = null;
                }
            }
            $this->setSessionVar(rex_login::SESSION_LAST_ACTIVITY, time());
        }

        if ($this->passkey) {
            $webauthn = new rex_webauthn();
            $result = $webauthn->processGet($this->passkey);

            if ($result) {
                [$this->passkey, $user] = $result;
                $this->setSessionVar(self::SESSION_USER_ID, $user->getId());
                $this->setSessionVar(self::SESSION_PASSWORD, null);
                $this->setSessionVar(self::SESSION_START_TIME, time());
                $this->setSessionVar(self::SESSION_LAST_ACTIVITY, time());
                $this->userLogin = null;
            } else {
                $this->message = rex_i18n::msg('login_error');
                $this->passkey = null;
            }
        }

        $check = parent::checkLogin();

        if ($check) {
            // gelungenen versuch speichern | login_tries = 0
            if ('' != $this->userLogin || !$userId) {
                self::regenerateSessionId();
                $params = [];
                $add = '';
                $password = $this->user->getValue('password');
                if ($password && $this->userLogin && $this->userPassword && self::passwordNeedsRehash($password)) {
                    $add .= 'password = ?, ';
                    $params[] = $password = self::passwordHash($this->userPassword, true);
                }
                array_push($params, rex_sql::datetime(), rex_sql::datetime(), session_id(), $this->getSessionVar(self::SESSION_USER_ID));
                $sql->setQuery('UPDATE ' . $this->tableName . ' SET ' . $add . 'login_tries=0, lasttrydate=?, lastlogin=?, session_id=? WHERE id=? LIMIT 1', $params);

                $this->setSessionVar(self::SESSION_PASSWORD, $password);

                if ($this->stayLoggedIn || $loggedInViaCookie) {
                    if (!$cookiekey || !$loggedInViaCookie) {
                        $cookiekey = base64_encode(random_bytes(64));
                    }
                    self::setStayLoggedInCookie($cookiekey);
                    $this->setSessionVar(self::SESSION_STAY_LOGGED_IN, true);
                } else {
                    $cookiekey = null;
                    $this->setSessionVar(self::SESSION_STAY_LOGGED_IN, false);
                }

                rex_user_session::getInstance()->storeCurrentSession($this, $cookiekey, $this->passkey);
                rex_user_session::clearExpiredSessions();
            }

            assert($this->user instanceof rex_sql);
            $this->user = rex_user::fromSql($this->user);

            if ($this->impersonator instanceof rex_sql) {
                $this->impersonator = rex_user::fromSql($this->impersonator);
            }

            if ($loggedInViaCookie || $this->userLogin) {
                if ($this->user->getValue('password_change_required')) {
                    $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, true);
                } elseif ($forceRenewAfter = $this->passwordPolicy->getForceRenewAfter()) {
                    $datetime = (new DateTimeImmutable())->sub($forceRenewAfter);
                    if (strtotime($this->user->getValue('password_changed')) < $datetime->getTimestamp()) {
                        $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, true);
                    }
                }
            }
            rex_user_session::getInstance()->updateLastActivity($this);
        } else {
            // fehlversuch speichern | login_tries++
            if ('' != $this->userLogin) {
                $sql->setQuery('SELECT login_tries FROM ' . $this->tableName . ' WHERE login=? LIMIT 1', [$this->userLogin]);
                if ($sql->getRows() > 0) {
                    $loginPolify = $this->getLoginPolicy();

                    $loginTries = $sql->getValue('login_tries');
                    $this->increaseLoginTries();
                    if ($loginTries >= $loginPolify->getMaxTriesUntilDelay() - 1) {
                        $time = $loginPolify->getReloginDelay();
                        $hours = floor($time / 3600);
                        $mins = floor(($time - ($hours * 3600)) / 60);
                        $secs = $time % 60;
                        $formatted = ($hours ? $hours . 'h ' : '') . ($hours || $mins ? $mins . 'min ' : '') . $secs . 's';
                        $this->message .= ' ' . rex_i18n::rawMsg('login_wait', '<strong data-time="' . $time . '">' . $formatted . '</strong>');
                    }
                }
            }
        }

        // check if session was killed only if the user is logged in
        if ($check) {
            $sql->setQuery('SELECT passkey_id FROM ' . rex::getTable('user_session') . ' where session_id = ?', [session_id()]);
            if (0 === $sql->getRows()) {
                $check = false;
                $this->message = rex_i18n::msg('login_session_expired');
                rex_csrf_token::removeAll();
            } else {
                $this->passkey = null === $sql->getValue('passkey_id') ? null : (string) $sql->getValue('passkey_id');
                if ($this->passkey) {
                    $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, false);
                }
            }
        }

        if ($this->isLoggedOut() && '' != $userId) {
            $sql->setQuery('UPDATE ' . $this->tableName . ' SET session_id="" WHERE id=? LIMIT 1', [$userId]);
            self::deleteStayLoggedInCookie();
            rex_user_session::getInstance()->clearCurrentSession();
        }

        return $check;
    }

    public function increaseLoginTries(): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('UPDATE ' . $this->tableName . ' SET login_tries=login_tries+1,session_id="",lasttrydate=? WHERE login=? LIMIT 1', [rex_sql::datetime(), $this->userLogin]);
    }

    public function requiresPasswordChange(): bool
    {
        return (bool) $this->getSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, false);
    }

    public function changedPassword(#[SensitiveParameter] ?string $passwordHash = null): void
    {
        $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, false);

        parent::changedPassword($passwordHash);

        if (null !== $user = $this->getUser()) {
            rex_user_session::getInstance()->removeSessionsExceptCurrent($user->getId());
        }
    }

    public function getPasskey(): ?string
    {
        return $this->passkey;
    }

    /** @return void */
    public static function deleteSession()
    {
        self::startSession();

        unset($_SESSION[static::getSessionNamespace()][self::SYSTEM_ID]);
        self::deleteStayLoggedInCookie();

        rex_csrf_token::removeAll();
    }

    private static function setStayLoggedInCookie(string $cookiekey): void
    {
        $sessionConfig = rex::getProperty('session', [])['backend']['cookie'] ?? [];

        rex_response::sendCookie(self::getStayLoggedInCookieName(), $cookiekey, [
            'expires' => strtotime(rex_user_session::STAY_LOGGED_IN_DURATION . ' months'),
            'secure' => $sessionConfig['secure'] ?? false,
            'samesite' => $sessionConfig['samesite'] ?? 'lax',
        ]);
    }

    private static function deleteStayLoggedInCookie(): void
    {
        rex_response::sendCookie(self::getStayLoggedInCookieName(), '');
    }

    /** @return string */
    public static function getStayLoggedInCookieName()
    {
        $instname = rex::getProperty('instname');
        if (!$instname) {
            throw new rex_exception('Property "instname" is empty');
        }

        return 'rex_user_' . sha1($instname);
    }

    /** @return bool */
    public static function hasSession()
    {
        // try to fast-fail, so we dont need to start a session in all cases (which would require a session lock...)
        if (!isset($_COOKIE[session_name()])) {
            return false;
        }

        // it is not possible to start a session if headers are already sent
        if (PHP_SESSION_ACTIVE !== session_status() && headers_sent()) {
            return false;
        }

        self::startSession();

        return ($_SESSION[static::getSessionNamespace()][self::SYSTEM_ID][rex_login::SESSION_USER_ID] ?? 0) > 0;
    }

    /**
     * Creates the user object if it does not already exist.
     *
     * Helpful if you want to check permissions of the backend user in frontend.
     * If you only want to know if there is any backend session, use {@link rex_backend_login::hasSession()}.
     *
     * @return rex_user|null
     */
    public static function createUser()
    {
        if (!self::hasSession()) {
            return null;
        }
        if ($user = rex::getUser()) {
            return $user;
        }

        $login = new self();
        rex::setProperty('login', $login);
        if ($login->checkLogin()) {
            $user = $login->getUser();
            rex::setProperty('user', $user);
            return $user;
        }
        return null;
    }

    /**
     * returns the backends session namespace.
     *
     * @return string
     */
    protected static function getSessionNamespace()
    {
        return rex::getProperty('instname') . '_backend';
    }

    public function getLoginPolicy(): rex_login_policy
    {
        $loginPolicy = (array) rex::getProperty('backend_login_policy', []);

        return new rex_login_policy($loginPolicy);
    }

    /**
     * @internal
     * @param rex_extension_point<null> $ep
     */
    public static function sessionRegenerated(rex_extension_point $ep): void
    {
        if (self::class === $ep->getParam('class')) {
            return;
        }

        rex_user_session::updateSessionId(rex_type::string($ep->getParam('previous_id')), rex_type::string($ep->getParam('new_id')));
    }
}
