<?php

namespace Redaxo\Core\Security;

use DateTimeImmutable;
use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use SensitiveParameter;

use function assert;

use const PHP_SESSION_ACTIVE;

/**
 * @method User|null getUser()
 * @method User|null getImpersonator()
 *
 * @final
 */
class BackendLogin extends Login
{
    public const string SYSTEM_ID = 'backend_login';
    public const string SESSION_STAY_LOGGED_IN = 'stay_logged_in';

    private const string SESSION_PASSWORD_CHANGE_REQUIRED = 'password_change_required';

    private static ?string $legacySha1Hash = null;

    private readonly string $tableName;
    private ?string $passkey = null;
    private bool $stayLoggedIn = false;
    private readonly BackendPasswordPolicy $passwordPolicy;

    public function __construct()
    {
        parent::__construct();

        $tableName = Core::getTablePrefix() . 'user';
        $this->systemId = self::SYSTEM_ID;
        $this->sessionDuration = Core::getProperty('session_duration');
        $qry = 'SELECT * FROM ' . $tableName;
        $this->userQuery = $qry . ' WHERE id = :id AND status = 1';
        $this->impersonateQuery = $qry . ' WHERE id = :id';
        $this->passwordPolicy = BackendPasswordPolicy::factory();

        $loginPolicy = $this->getLoginPolicy();

        // XXX because with concat the time into the sql query, users of this class should use checkLogin() immediately after creating the object.
        $qry .= ' WHERE
            status = 1
            AND login = :login
            AND login_tries < ' . $loginPolicy->getMaxTriesUntilBlock() . '
            AND (
                login_tries < ' . $loginPolicy->getMaxTriesUntilDelay() . '
                OR
                login_tries >= ' . $loginPolicy->getMaxTriesUntilDelay() . ' AND lasttrydate < "' . Sql::datetime(time() - $loginPolicy->getReloginDelay()) . '"
            )';

        if ($blockAccountAfter = $this->passwordPolicy->blockAccountAfter) {
            $datetime = new DateTimeImmutable()->sub($blockAccountAfter);
            $qry .= ' AND password_changed > "' . $datetime->format(Sql::FORMAT_DATETIME) . '"';
        }

        $this->loginQuery = $qry;

        $this->tableName = $tableName;
    }

    public function setPasskey(?string $data): void
    {
        $this->passkey = $data;
    }

    /** @param bool $stayLoggedIn */
    public function setStayLoggedIn($stayLoggedIn = false): void
    {
        if (!$this->getLoginPolicy()->isStayLoggedInEnabled()) {
            $stayLoggedIn = false;
        }

        $this->stayLoggedIn = (bool) $stayLoggedIn;
    }

    public function checkLogin(): bool
    {
        $sql = Sql::factory();
        $userId = $this->getSessionVar(Login::SESSION_USER_ID);
        $cookiename = self::getStayLoggedInCookieName();
        $loggedInViaCookie = false;

        if ($cookiekey = Request::cookie($cookiename, 'string', null)) {
            if (!$userId) {
                $sql->setQuery('
                    SELECT id, password
                    FROM ' . Core::getTable('user') . ' user
                    JOIN ' . Core::getTable('user_session') . ' ON user.id = user_id
                    WHERE cookie_key = ?
                    LIMIT 1
                ', [$cookiekey]);
                if (1 == $sql->getRows()) {
                    $this->setSessionVar(Login::SESSION_USER_ID, $sql->getValue('id'));
                    $this->setSessionVar(Login::SESSION_PASSWORD, $sql->getValue('password'));
                    self::setStayLoggedInCookie($cookiekey);
                    $loggedInViaCookie = true;
                } else {
                    self::deleteStayLoggedInCookie();
                    $cookiekey = null;
                }
            }
            $this->setSessionVar(Login::SESSION_LAST_ACTIVITY, time());
        }

        if ($this->passkey) {
            $webauthn = new WebAuthn();
            $result = $webauthn->processGet($this->passkey);

            if ($result) {
                [$this->passkey, $user] = $result;
                $this->setSessionVar(self::SESSION_USER_ID, $user->id);
                $this->setSessionVar(self::SESSION_PASSWORD, null);
                $this->setSessionVar(self::SESSION_START_TIME, time());
                $this->setSessionVar(self::SESSION_LAST_ACTIVITY, time());
                $this->userLogin = null;
            } else {
                $this->message = I18n::msg('login_error');
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
                    $params[] = $password = self::passwordHash($this->userPassword);
                }
                array_push($params, Sql::datetime(), Sql::datetime(), session_id(), $this->getSessionVar(self::SESSION_USER_ID));
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

                UserSession::getInstance()->storeCurrentSession($this, $cookiekey, $this->passkey);
                UserSession::clearExpiredSessions();
            }

            assert($this->user instanceof Sql);
            $this->user = User::fromSql($this->user);

            if ($this->impersonator instanceof Sql) {
                $this->impersonator = User::fromSql($this->impersonator);
            }

            if ($loggedInViaCookie || $this->userLogin) {
                if ($this->user->getValue('password_change_required')) {
                    $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, true);
                } elseif ($forceRenewAfter = $this->passwordPolicy->forceRenewAfter) {
                    $datetime = new DateTimeImmutable()->sub($forceRenewAfter);
                    if (strtotime($this->user->getValue('password_changed')) < $datetime->getTimestamp()) {
                        $this->setSessionVar(self::SESSION_PASSWORD_CHANGE_REQUIRED, true);
                    }
                }
            }
            UserSession::getInstance()->updateLastActivity($this);
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
                        $this->message .= ' ' . I18n::rawMsg('login_wait', '<strong data-time="' . $time . '">' . $formatted . '</strong>');
                    }
                }
            }
        }

        // check if session was killed only if the user is logged in
        if ($check) {
            $sql->setQuery('SELECT passkey_id FROM ' . Core::getTable('user_session') . ' where session_id = ?', [session_id()]);
            if (0 === $sql->getRows()) {
                $check = false;
                $this->message = I18n::msg('login_session_expired');
                CsrfToken::removeAll();
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
            UserSession::getInstance()->clearCurrentSession();
        }

        return $check;
    }

    public function increaseLoginTries(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('UPDATE ' . $this->tableName . ' SET login_tries=login_tries+1,session_id="",lasttrydate=? WHERE login=? LIMIT 1', [Sql::datetime(), $this->userLogin]);
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
            UserSession::getInstance()->removeSessionsExceptCurrent($user->id);
        }
    }

    public function getPasskey(): ?string
    {
        return $this->passkey;
    }

    public static function deleteSession(): void
    {
        self::startSession();

        unset($_SESSION[static::getSessionNamespace()][self::SYSTEM_ID]);
        self::deleteStayLoggedInCookie();

        CsrfToken::removeAll();
    }

    private static function setStayLoggedInCookie(string $cookiekey): void
    {
        $sessionConfig = Core::getProperty('session', [])['backend']['cookie'] ?? [];

        Response::sendCookie(self::getStayLoggedInCookieName(), $cookiekey, [
            'expires' => strtotime(UserSession::STAY_LOGGED_IN_DURATION . ' months'),
            'secure' => $sessionConfig['secure'] ?? false,
            'samesite' => $sessionConfig['samesite'] ?? 'lax',
        ]);
    }

    private static function deleteStayLoggedInCookie(): void
    {
        Response::sendCookie(self::getStayLoggedInCookieName(), '');
    }

    public static function getStayLoggedInCookieName(): string
    {
        $instname = Core::getProperty('instname');
        if (!$instname) {
            throw new LogicException('Property "instname" is empty');
        }

        return 'rex_user_' . sha1($instname);
    }

    public static function hasSession(): bool
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

        return ($_SESSION[static::getSessionNamespace()][self::SYSTEM_ID][Login::SESSION_USER_ID] ?? 0) > 0;
    }

    /**
     * Creates the user object if it does not already exist.
     *
     * Helpful if you want to check permissions of the backend user in frontend.
     * If you only want to know if there is any backend session, use {@link BackendLogin::hasSession()}.
     */
    public static function createUser(): ?User
    {
        if (!self::hasSession()) {
            return null;
        }
        if ($user = Core::getUser()) {
            return $user;
        }

        $login = new self();
        Core::setProperty('login', $login);
        if ($login->checkLogin()) {
            $user = $login->getUser();
            Core::setProperty('user', $user);
            return $user;
        }
        return null;
    }

    #[Override]
    public static function passwordVerify(#[SensitiveParameter] string $password, #[SensitiveParameter] string $hash): bool
    {
        if (parent::passwordVerify($password, $hash)) {
            return true;
        }

        // Fallback for legacy passwords from REDAXO 5.x which were sha1-prehashed
        if (password_verify(sha1($password), $hash)) {
            self::$legacySha1Hash = $hash;
            return true;
        }

        return false;
    }

    #[Override]
    public static function passwordNeedsRehash(#[SensitiveParameter] string $hash): bool
    {
        if ($hash === self::$legacySha1Hash) {
            return true;
        }

        return parent::passwordNeedsRehash($hash);
    }

    /** returns the backends session namespace. */
    protected static function getSessionNamespace(): string
    {
        return Core::getProperty('instname') . '_backend';
    }

    public function getLoginPolicy(): LoginPolicy
    {
        $loginPolicy = (array) Core::getProperty('backend_login_policy', []);

        return new LoginPolicy($loginPolicy);
    }

    /**
     * @internal
     * @param ExtensionPoint<null> $ep
     */
    public static function sessionRegenerated(ExtensionPoint $ep): void
    {
        if (self::class === $ep->getParam('class')) {
            return;
        }

        UserSession::updateSessionId(Type::string($ep->getParam('previous_id')), Type::string($ep->getParam('new_id')));
    }
}
