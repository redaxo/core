<?php

namespace Redaxo\Core\Http;

use Redaxo\Core\Exception\LogicException;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

use function session_status;

use const PHP_SESSION_ACTIVE;

/**
 * Session storage which refuses to start a session implicitly.
 *
 * http-foundation starts the session as soon as a bag is accessed. A session started by a mere read sends a
 * cookie for every visitor and makes the response uncacheable, so it has to be asked for explicitly.
 *
 * @internal
 */
final class SessionStorage extends NativeSessionStorage
{
    public function getBag(string $name): SessionBagInterface
    {
        // `closed` means the session was started and closed again to release its lock, its data stays readable
        if (!$this->started && !$this->closed && PHP_SESSION_ACTIVE !== session_status()) {
            throw new LogicException('Session not started, call Session::start() before.');
        }

        return parent::getBag($name);
    }
}
