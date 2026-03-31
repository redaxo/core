<?php

namespace Redaxo\Core\Http;

use Closure;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Util\Type;

use function array_key_exists;
use function is_array;
use function is_scalar;

use const PHP_SAPI;
use const PHP_SESSION_ACTIVE;

/**
 * Class for getting the superglobals.
 *
 * @psalm-import-type TCastType from Type
 */
final class Request
{
    private function __construct() {}

    /**
     * Returns the variable $varname of $_GET and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     *
     * @psalm-taint-escape ($type is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function get(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_GET, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_POST and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     *
     * @psalm-taint-escape ($type is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function post(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_POST, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_REQUEST and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     *
     * @psalm-taint-escape ($type is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function request(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_REQUEST, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_SERVER and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     */
    public static function server(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_SERVER, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_SESSION and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     */
    public static function session(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new LogicException('Session not started, call Login::startSession() before.');
        }

        if (isset($_SESSION[self::getSessionNamespace()][$varname])) {
            return Type::cast($_SESSION[self::getSessionNamespace()][$varname], $type);
        }

        if ('' === $default) {
            return Type::cast($default, $type);
        }
        return $default;
    }

    /**
     * Sets a session variable.
     *
     * @param string $varname Variable name
     * @param mixed $value Value
     */
    public static function setSession(string $varname, mixed $value): void
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new LogicException('Session not started, call Login::startSession() before.');
        }

        $_SESSION[self::getSessionNamespace()][$varname] = $value;
    }

    /**
     * Deletes a session variable.
     *
     * @param string $varname Variable name
     */
    public static function unsetSession(string $varname): void
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new LogicException('Session not started, call Login::startSession() before.');
        }

        unset($_SESSION[self::getSessionNamespace()][$varname]);
    }

    /** clear redaxo session contents within the current namespace (the session itself stays alive). */
    public static function clearSession(): void
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new LogicException('Session not started, call Login::startSession() before.');
        }

        unset($_SESSION[self::getSessionNamespace()]);
    }

    /**
     * Returns the variable $varname of $_COOKIE and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     *
     * @psalm-taint-escape ($type is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function cookie(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_COOKIE, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_FILES and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     */
    public static function files(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_FILES, $varname, $type, $default);
    }

    /**
     * Returns the variable $varname of $_ENV and casts the value.
     *
     * @param string $varname Variable name
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     */
    public static function env(string $varname, string|array|Closure|null $type = null, mixed $default = ''): mixed
    {
        return self::arrayKeyCast($_ENV, $varname, $type, $default);
    }

    /**
     * Searches the value $needle in array $haystack and returns the casted value.
     *
     * @param array<mixed> $haystack Array
     * @param string $needle Value to search
     * @param TCastType $type Cast type
     * @param mixed $default Default value
     *
     * @psalm-taint-specialize
     */
    private static function arrayKeyCast(array $haystack, string $needle, string|array|Closure|null $type, mixed $default = ''): mixed
    {
        if (array_key_exists($needle, $haystack)) {
            if (is_array($type) && '' !== $default && is_scalar($type[0] ?? null) && $type[0] !== $default) {
                array_unshift($type, $default);
            }

            return Type::cast($haystack[$needle], $type);
        }

        if ('' === $default) {
            return Type::cast($default, $type);
        }
        return $default;
    }

    /**
     * Returns the HTTP method of the current request.
     *
     * @return lowercase-string HTTP method in lowercase (head,get,post,put,delete)
     */
    public static function requestMethod(): string
    {
        return strtolower(Core::getRequest()->getMethod());
    }

    /**
     * Returns true if the request is a XMLHttpRequest.
     *
     * This only works if your javaScript library sets an X-Requested-With HTTP header.
     * This is the case with Prototype, Mootools, jQuery, and perhaps others.
     *
     * Inspired by a method of the symfony framework.
     *
     * @return bool true if the request is an XMLHttpRequest, false otherwise
     */
    public static function isXmlHttpRequest(): bool
    {
        return Core::getRequest()->isXmlHttpRequest();
    }

    /**
     * Returns true if the request is a PJAX-Request.
     *
     * @see http://pjax.heroku.com/
     */
    public static function isPJAXRequest(): bool
    {
        if ('cli' === PHP_SAPI) {
            return false;
        }

        return 'true' == Core::getRequest()->headers->get('X-Pjax');
    }

    /** Returns true when the current request is a PJAX-Request and the requested container matches the given $containerId. */
    public static function isPJAXContainer(string $containerId): bool
    {
        if (!self::isPJAXRequest()) {
            return false;
        }

        return $containerId === Core::getRequest()->headers->get('X-Pjax-Container');
    }

    /**
     * Returns whether the current request is served via https/ssl.
     *
     * @return bool true when https/ssl, otherwise false
     */
    public static function isHttps(): bool
    {
        return Core::getRequest()->isSecure();
    }

    /**
     * Returns the session namespace for the current http request.
     *
     * @return non-empty-string
     */
    public static function getSessionNamespace(): string
    {
        // separate backend from frontend namespace,
        // so we can e.g. clear the backend session without
        // logging out the users from the frontend
        $suffix = Core::isBackend() ? '_backend' : '';
        return Core::getProperty('instname') . $suffix;
    }
}
