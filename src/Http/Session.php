<?php

namespace Redaxo\Core\Http;

use Redaxo\Core\Core;
use Redaxo\Core\Util\Timer;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Session as HttpFoundationSession;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use function assert;
use function session_status;

use const PHP_SAPI;
use const PHP_SESSION_ACTIVE;

/**
 * The http session.
 *
 * Start it with `Session::start()`, afterwards it is available as `Core::getRequest()->getSession()`.
 * A session is never started implicitly, so that a request which does not need one stays cacheable.
 */
final class Session
{
    /** Name of the session bag holding the backend attributes. */
    private const string BAG_BACKEND = 'backend';

    /** Name of the session bag holding the frontend attributes. */
    private const string BAG_FRONTEND = 'frontend';

    /**
     * Options for the session in the backend.
     *
     * The keys are the `session.*` ini settings without their prefix, see https://php.net/session.configuration.
     * `cookie_secure` additionally understands `auto`, which sets the flag for requests over https.
     *
     * @var array<string, scalar>
     */
    public static array $backendOptions = [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => 'auto',
    ];

    /**
     * Options for the session in the frontend.
     *
     * @var array<string, scalar>
     */
    public static array $frontendOptions = [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => 'auto',
    ];

    /** Handler storing the backend session, `null` for the one configured in the php settings. */
    public static ?SessionHandlerInterface $backendHandler = null;

    /** Handler storing the frontend session, `null` for the one configured in the php settings. */
    public static ?SessionHandlerInterface $frontendHandler = null;

    private static ?SessionInterface $session = null;

    private function __construct() {}

    /** Starts the session, if it is not started yet. */
    public static function start(): SessionInterface
    {
        $session = self::get();

        if (!$session->isStarted()) {
            Timer::measure(__METHOD__, static fn () => $session->start());
        }

        return $session;
    }

    /**
     * Writes the session and releases its lock, so that parallel requests are not blocked while a long response
     * is sent. Changes made afterwards are not persisted.
     */
    public static function close(): void
    {
        if (self::$session?->isStarted()) {
            self::$session->save();
        }
    }

    /**
     * Discards the changes made in this request and releases the lock, see https://php.net/session_abort.
     *
     * http-foundation has no equivalent, so the session is closed on the php level and the session object is
     * dropped, to not leave it claiming that it is started.
     */
    public static function abort(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_abort();
        }

        self::$session = null;
    }

    /**
     * Returns the session without starting it.
     *
     * @internal
     */
    public static function get(): SessionInterface
    {
        return self::$session ??= self::create();
    }

    /**
     * Namespace the attributes of the current environment are stored under in `$_SESSION`.
     *
     * @return non-empty-string
     */
    public static function getNamespace(): string
    {
        return Core::getInstanceId() . (Core::isBackend() ? '_backend' : '');
    }

    /** Returns the attributes of the current environment. */
    public static function getAttributes(): AttributeBagInterface
    {
        return self::getBag(Core::isBackend() ? self::BAG_BACKEND : self::BAG_FRONTEND);
    }

    /**
     * Returns the attributes of the backend session, also in the frontend, where a logged in backend user is
     * detected this way.
     */
    public static function getBackendAttributes(): AttributeBagInterface
    {
        return self::getBag(self::BAG_BACKEND);
    }

    /**
     * Returns the cookie parameters of the session, see https://php.net/session_get_cookie_params.
     *
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public static function getCookieParams(): array
    {
        // creating the session applies the options to the php settings, which the cookie params are read from
        self::get();

        $params = session_get_cookie_params();

        return [
            'lifetime' => $params['lifetime'] ?? 0,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? false,
            'samesite' => $params['samesite'] ?? '',
        ];
    }

    private static function create(): SessionInterface
    {
        $backend = Core::isBackend();

        $options = $backend ? self::$backendOptions : self::$frontendOptions;

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $options['cookie_secure'] = 'cli' !== PHP_SAPI && Request::isHttps();
        }

        $storage = new SessionStorage($options, $backend ? self::$backendHandler : self::$frontendHandler);

        // both environments keep their attributes apart, so that e.g. the backend session can be cleared without
        // logging out the users in the frontend
        $backendBag = new AttributeBag(Core::getInstanceId() . '_backend');
        $backendBag->setName(self::BAG_BACKEND);
        $frontendBag = new AttributeBag(Core::getInstanceId());
        $frontendBag->setName(self::BAG_FRONTEND);

        $session = new HttpFoundationSession($storage, $backend ? $backendBag : $frontendBag);
        $session->registerBag($backend ? $frontendBag : $backendBag);

        return $session;
    }

    private static function getBag(string $name): AttributeBagInterface
    {
        $bag = self::start()->getBag($name);
        assert($bag instanceof AttributeBagInterface);

        return $bag;
    }
}
