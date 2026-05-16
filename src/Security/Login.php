<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use SensitiveParameter;

use function sprintf;

use const PASSWORD_DEFAULT;
use const PHP_SESSION_ACTIVE;

class Login
{
    /** Session ID is saved in session under this key for session fixation prevention. */
    final public const string SESSION_ID = 'REX_SESSID';
    /** the timestamp when the session was initially started. */
    final public const string SESSION_START_TIME = 'starttime';
    /** a timestamp of the last activity of the http session. */
    final public const string SESSION_LAST_ACTIVITY = 'STAMP';
    /** the id of the user. */
    final public const string SESSION_USER_ID = 'UID';
    /** the encrypted user password. */
    final public const string SESSION_PASSWORD = 'password';
    /** the userid of the impersonator user. */
    final public const string SESSION_IMPERSONATOR = 'impersonator';

    /** @var positive-int */
    protected int $DB = 1;

    /** A Session will be closed when not activly used for this timespan (seconds). */
    protected int $sessionDuration = 0;
    /** A session cannot stay longer then this value, no matter its actively used once in a while (seconds). */
    protected int $sessionMaxOverallDuration;

    protected string $loginQuery;
    protected string $userQuery;
    protected ?string $impersonateQuery = null;

    protected string $systemId = 'default';

    protected ?string $userLogin = null;
    protected ?string $userPassword = null;
    protected bool $logout = false;

    protected string $idColumn = 'id';
    protected string $passwordColumn = 'password';

    protected bool $cache = false;
    protected int $loginStatus = 0; // 0 = noch checken, 1 = ok, -1 = not ok
    protected string $message = '';

    protected Sql|User|null $user = null;
    protected Sql|User|null $impersonator = null;

    public function __construct()
    {
        $this->sessionMaxOverallDuration = Core::getProperty('session_max_overall_duration', 2_419_200); // 4 weeks

        self::startSession();
    }

    /** Setzt den Login und das Password. */
    public function setLogin(#[SensitiveParameter] string $login, #[SensitiveParameter] string $password): void
    {
        $this->userLogin = $login;
        $this->userPassword = $password;
    }

    /** Markiert die aktuelle Session als ausgeloggt. */
    public function setLogout(bool $logout): void
    {
        $this->logout = $logout;
    }

    /** Prüft, ob die aktuelle Session ausgeloggt ist. */
    public function isLoggedOut(): bool
    {
        return $this->logout;
    }

    /** Returns the message. */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Prüft die mit setLogin() und setPassword() gesetzten Werte
     * anhand des LoginQueries/UserQueries und gibt den Status zurück.
     *
     * Gibt true zurück bei erfolg, sonst false
     */
    public function checkLogin(): bool
    {
        // wenn logout dann header schreiben und auf error seite verweisen
        // message schreiben

        $ok = false;

        if (!$this->logout) {
            // LoginStatus: 0 = noch checken, 1 = ok, -1 = not ok

            // gecachte ausgabe erlaubt ? checkLogin schonmal ausgeführt ?
            if ($this->cache && 0 != $this->loginStatus) {
                return $this->loginStatus > 0;
            }

            if ('' != $this->userLogin) {
                // wenn login daten eingegeben dann checken
                // auf error seite verweisen und message schreiben

                $this->user = Sql::factory($this->DB);

                $this->user->setQuery($this->loginQuery, [':login' => $this->userLogin]);
                if (1 == $this->user->getRows() && static::passwordVerify($this->userPassword, (string) $this->user->getValue($this->passwordColumn))) {
                    $ok = true;
                    static::regenerateSessionId();
                    $this->setSessionVar(self::SESSION_START_TIME, time());
                    $this->setSessionVar(self::SESSION_USER_ID, $this->user->getValue($this->idColumn));
                    $this->setSessionVar(self::SESSION_PASSWORD, $this->user->getValue($this->passwordColumn));
                } else {
                    $this->message = I18n::msg('login_error');
                }
            } elseif ('' != $this->getSessionVar(self::SESSION_USER_ID)) {
                // wenn kein login und kein logout dann nach sessiontime checken
                // message schreiben und falls falsch auf error verweisen

                $ok = true;

                // add property if missing from the session.
                // not only on start, but everytime, to support migration of pre-existing sessions
                $sessionStartTime = $this->getSessionVar(self::SESSION_START_TIME, null);
                if (null === $sessionStartTime) {
                    $sessionStartTime = time();
                    $this->setSessionVar(self::SESSION_START_TIME, $sessionStartTime);
                }
                // check session max age
                if (($sessionStartTime + $this->sessionMaxOverallDuration) < time()) {
                    $ok = false;
                    $this->message = I18n::msg('login_session_expired');

                    CsrfToken::removeAll();
                }

                // check session last activity
                $sessionLastActivityStamp = (int) $this->getSessionVar(self::SESSION_LAST_ACTIVITY);
                if (($sessionLastActivityStamp + $this->sessionDuration) < time()) {
                    $ok = false;
                    $this->message = I18n::msg('login_session_expired');

                    CsrfToken::removeAll();
                }

                if ($ok && $impersonator = $this->getSessionVar(self::SESSION_IMPERSONATOR)) {
                    $this->impersonator = Sql::factory($this->DB);
                    $this->impersonator->setQuery($this->userQuery, [':id' => $impersonator]);

                    if (!$this->impersonator->getRows()) {
                        $ok = false;
                        $this->message = I18n::msg('login_user_not_found');
                    } elseif (
                        null !== ($sessionPassword = $this->getSessionVar(self::SESSION_PASSWORD, null))
                        && $this->impersonator->getValue($this->passwordColumn) !== $sessionPassword
                    ) {
                        $ok = false;
                        $this->message = I18n::msg('login_session_expired');
                    }
                }

                if ($ok) {
                    $query = $this->impersonator && $this->impersonateQuery ? $this->impersonateQuery : $this->userQuery;
                    $this->user = Sql::factory($this->DB);
                    $this->user->setQuery($query, [':id' => $this->getSessionVar(self::SESSION_USER_ID)]);

                    if (!$this->user->getRows()) {
                        $ok = false;
                        $this->message = I18n::msg('login_user_not_found');
                    } elseif (
                        !$this->impersonator
                        && null !== ($sessionPassword = $this->getSessionVar(self::SESSION_PASSWORD, null))
                        && (string) $this->user->getValue($this->passwordColumn) !== $sessionPassword
                    ) {
                        $ok = false;
                        $this->message = I18n::msg('login_session_expired');
                    }
                }
            }
        } else {
            $this->message = I18n::msg('login_logged_out');

            CsrfToken::removeAll();
        }

        if ($ok) {
            // wenn alles ok dann REX[UID][system_id] schreiben
            $this->setSessionVar(self::SESSION_LAST_ACTIVITY, time());

            // each code-path which set $ok=true, must also set a UID
            $sessUid = $this->getSessionVar(self::SESSION_USER_ID);
            if (empty($sessUid)) {
                throw new LogicException('Login considered successfull but no UID found');
            }
        } else {
            // wenn nicht, dann UID loeschen und error seite
            $this->setSessionVar(self::SESSION_LAST_ACTIVITY, '');
            $this->setSessionVar(self::SESSION_USER_ID, '');
            $this->setSessionVar(self::SESSION_IMPERSONATOR, null);
            $this->setSessionVar(self::SESSION_PASSWORD, null);
        }

        $this->loginStatus = $ok ? 1 : -1;

        return $ok;
    }

    public function impersonate(int $id): void
    {
        if (!$this->user) {
            throw new RuntimeException('Can not impersonate a user without valid user session.');
        }
        if ($this->user->getValue($this->idColumn) == $id) {
            throw new RuntimeException('Can not impersonate the current user.');
        }

        $user = Sql::factory($this->DB);
        $user->setQuery($this->impersonateQuery ?: $this->userQuery, [':id' => $id]);

        if (!$user->getRows()) {
            throw new RuntimeException(sprintf('User with id "%d" not found.', $id));
        }

        $this->impersonator = $this->user;
        $this->user = $user;

        $this->setSessionVar(self::SESSION_USER_ID, $id);
        $this->setSessionVar(self::SESSION_IMPERSONATOR, $this->impersonator->getValue($this->idColumn));
    }

    public function depersonate(): void
    {
        if (!$this->impersonator) {
            return;
        }

        $this->user = $this->impersonator;
        $this->impersonator = null;

        $this->setSessionVar(self::SESSION_USER_ID, $this->user->getValue($this->idColumn));
        $this->setSessionVar(self::SESSION_IMPERSONATOR, null);
    }

    public function changedPassword(#[SensitiveParameter] ?string $passwordHash): void
    {
        $this->setSessionVar(self::SESSION_PASSWORD, $passwordHash);
    }

    public function getUser(): User|Sql|null
    {
        return $this->user;
    }

    public function getImpersonator(): User|Sql|null
    {
        return $this->impersonator;
    }

    /** Gibt einen Benutzer-Spezifischen Wert zurück. */
    public function getValue(string $key, mixed $default = null): mixed
    {
        if ($this->user) {
            return $this->user->getValue($key);
        }

        return $default;
    }

    /**
     * Setzte eine Session-Variable.
     *
     * @param scalar|array|null $value
     */
    public function setSessionVar(string $varname, mixed $value): void
    {
        $_SESSION[static::getSessionNamespace()][$this->systemId][$varname] = $value;
    }

    /**
     * Gibt den Wert einer Session-Variable zurück.
     *
     * @return (
     *     $varname is 'starttime' ? int|null :
     *     ($varname is 'STAMP' ? int|null :
     *     ($varname is 'UID' ? int|null :
     *     ($varname is 'password' ? string|null :
     *     ($varname is 'impersonator' ? int|null :
     *     ($varname is 'last_db_update' ? int|null :
     *     mixed
     *     )))))
     * )
     */
    public function getSessionVar(string $varname, mixed $default = ''): mixed
    {
        /** @var bool $sessChecked */
        static $sessChecked = false;
        // validate session-id - once per request - to prevent fixation
        if (!$sessChecked) {
            $rexSessId = !empty($_SESSION[self::SESSION_ID]) ? $_SESSION[self::SESSION_ID] : '';

            if (!empty($rexSessId) && $rexSessId !== session_id()) {
                // clear redaxo related session properties on a possible attack
                $_SESSION[static::getSessionNamespace()][$this->systemId] = [];
            }
            $sessChecked = true;
        }

        return $_SESSION[static::getSessionNamespace()][$this->systemId][$varname] ?? $default;
    }

    /** refresh session on permission elevation for security reasons. */
    public static function regenerateSessionId(): void
    {
        /** @var bool $regenerated */
        static $regenerated = false;
        if ($regenerated) {
            return;
        }

        if ('' != $previous = session_id()) {
            $regenerated = true;

            session_regenerate_id(true);

            CsrfToken::removeAll();

            $extensionPoint = new ExtensionPoint('SESSION_REGENERATED', null, [
                'previous_id' => $previous,
                'new_id' => session_id(),
                'class' => static::class,
            ], true);

            // We don't know here if packages have already been loaded
            // Therefore we call the extension point twice, directly and after PACKAGES_INCLUDED
            Extension::registerPoint($extensionPoint);
            Extension::register('PACKAGES_INCLUDED', static function () use ($extensionPoint) {
                Extension::registerPoint($extensionPoint);
            }, Extension::EARLY);
        }

        // session-id is shared between frontend/backend or even redaxo instances per server because it's the same http session
        $_SESSION[self::SESSION_ID] = session_id();
    }

    /** starts a http-session if not already started. */
    public static function startSession(): void
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            $env = Core::isBackend() ? 'backend' : 'frontend';
            $sessionConfig = Type::array(Core::getProperty('session', []));

            if (isset($sessionConfig[$env]['save_path'])) {
                session_save_path((string) $sessionConfig[$env]['save_path']);
            }

            session_set_cookie_params(static::getCookieParams());

            Timer::measure(__METHOD__, static function () {
                error_clear_last();

                if (!@session_start()) {
                    if ($error = error_get_last()) {
                        throw new RuntimeException('Unable to start session: ' . $error['message']);
                    }
                    throw new RuntimeException('Unable to start session.');
                }
            });
        }
    }

    /**
     * Einstellen der Cookie Paramter bevor die session gestartet wird.
     *
     * @return array{lifetime: ?int, path: ?string, domain: ?string, secure: ?bool, httponly: ?bool, samesite: ?string}
     */
    public static function getCookieParams(): array
    {
        $cookieParams = session_get_cookie_params();

        $key = Core::isBackend() ? 'backend' : 'frontend';
        $sessionConfig = Core::getProperty('session', []);

        if ($sessionConfig) {
            foreach ($sessionConfig[$key]['cookie'] as $name => $value) {
                if (null !== $value) {
                    $cookieParams[$name] = $value;
                }
            }
        }

        return $cookieParams;
    }

    public static function passwordHash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function passwordVerify(#[SensitiveParameter] string $password, #[SensitiveParameter] string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function passwordNeedsRehash(#[SensitiveParameter] string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /** returns the current session namespace. */
    protected static function getSessionNamespace(): string
    {
        return Request::getSessionNamespace();
    }
}
