<?php

/**
 * Class for getting the superglobals.
 *
 * @package redaxo\core
 */
class rex_request
{
    /**
     * Returns the variable $varname of $_GET and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     *
     * @psalm-taint-escape ($vartype is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function get($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_GET, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_POST and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     *
     * @psalm-taint-escape ($vartype is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function post($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_POST, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_REQUEST and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     *
     * @psalm-taint-escape ($vartype is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function request($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_REQUEST, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_SERVER and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public static function server($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_SERVER, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_SESSION and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @throws rex_exception
     *
     * @return mixed
     */
    public static function session($varname, $vartype = '', $default = '')
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new rex_exception('Session not started, call rex_login::startSession() before!');
        }

        if (isset($_SESSION[self::getSessionNamespace()][$varname])) {
            return rex_type::cast($_SESSION[self::getSessionNamespace()][$varname], $vartype);
        }

        if ('' === $default) {
            return rex_type::cast($default, $vartype);
        }
        return $default;
    }

    /**
     * Sets a session variable.
     *
     * @param string $varname Variable name
     * @param mixed $value Value
     *
     * @throws rex_exception
     * @return void
     */
    public static function setSession($varname, $value)
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new rex_exception('Session not started, call rex_login::startSession() before!');
        }

        $_SESSION[self::getSessionNamespace()][$varname] = $value;
    }

    /**
     * Deletes a session variable.
     *
     * @param string $varname Variable name
     *
     * @throws rex_exception
     * @return void
     */
    public static function unsetSession($varname)
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new rex_exception('Session not started, call rex_login::startSession() before!');
        }

        unset($_SESSION[self::getSessionNamespace()][$varname]);
    }

    /**
     * clear redaxo session contents within the current namespace (the session itself stays alive).
     *
     * @throws rex_exception
     * @return void
     */
    public static function clearSession()
    {
        if (PHP_SESSION_ACTIVE != session_status()) {
            throw new rex_exception('Session not started, call rex_login::startSession() before!');
        }

        unset($_SESSION[self::getSessionNamespace()]);
    }

    /**
     * Returns the variable $varname of $_COOKIE and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     *
     * @psalm-taint-escape ($vartype is 'bool'|'boolean'|'int'|'integer'|'double'|'float'|'real' ? 'html' : null)
     */
    public static function cookie($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_COOKIE, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_FILES and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public static function files($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_FILES, $varname, $vartype, $default);
    }

    /**
     * Returns the variable $varname of $_ENV and casts the value.
     *
     * @param string $varname Variable name
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public static function env($varname, $vartype = '', $default = '')
    {
        return self::arrayKeyCast($_ENV, $varname, $vartype, $default);
    }

    /**
     * Searches the value $needle in array $haystack and returns the casted value.
     *
     * @param array $haystack Array
     * @param string|int $needle Value to search
     * @param mixed $vartype Variable type
     * @param mixed $default Default value
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     *
     * @psalm-taint-specialize
     */
    private static function arrayKeyCast(array $haystack, $needle, $vartype, $default = '')
    {
        if (!is_scalar($needle)) {
            throw new InvalidArgumentException('Scalar expected for $needle in arrayKeyCast(), got ' . gettype($needle) . '!');
        }

        if (array_key_exists($needle, $haystack)) {
            if (is_array($vartype) && '' !== $default && is_scalar($vartype[0] ?? null) && $vartype[0] !== $default) {
                array_unshift($vartype, $default);
            }

            return rex_type::cast($haystack[$needle], $vartype);
        }

        if ('' === $default) {
            return rex_type::cast($default, $vartype);
        }
        return $default;
    }

    /**
     * Returns the HTTP method of the current request.
     *
     * @return lowercase-string HTTP method in lowercase (head,get,post,put,delete)
     */
    public static function requestMethod()
    {
        return strtolower(rex::getRequest()->getMethod());
    }

    /**
     * Returns the effective upload size limit in bytes, considering both `upload_max_filesize` and `post_max_size`.
     *
     * @internal
     */
    public static function getMaxUploadSize(): int
    {
        $limits = array_filter([rex_ini_get('upload_max_filesize'), rex_ini_get('post_max_size')], static fn (int $limit) => $limit > 0);

        return $limits ? min($limits) : 0;
    }

    /**
     * Returns whether PHP discarded the request body ($_POST and $_FILES) because it exceeded `post_max_size`.
     *
     * @internal
     */
    public static function isPostMaxSizeExceeded(): bool
    {
        if ('post' !== self::requestMethod() || $_POST || $_FILES) {
            return false;
        }

        $postMaxSize = rex_ini_get('post_max_size');

        return $postMaxSize > 0 && self::server('CONTENT_LENGTH', 'int', 0) > $postMaxSize;
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
    public static function isXmlHttpRequest()
    {
        return rex::getRequest()->isXmlHttpRequest();
    }

    /**
     * Returns true if the request is a PJAX-Request.
     *
     * @see http://pjax.heroku.com/
     *
     * @return bool
     */
    public static function isPJAXRequest()
    {
        if ('cli' === PHP_SAPI) {
            return false;
        }

        return 'true' == rex::getRequest()->headers->get('X-Pjax');
    }

    /**
     * Returns true when the current request is a PJAX-Request and the requested container matches the given $containerId.
     *
     * @param string $containerId
     *
     * @return bool
     */
    public static function isPJAXContainer($containerId)
    {
        if (!self::isPJAXRequest()) {
            return false;
        }

        return $containerId === rex::getRequest()->headers->get('X-Pjax-Container');
    }

    /**
     * Returns whether the current request is served via https/ssl.
     *
     * @return bool true when https/ssl, otherwise false
     */
    public static function isHttps()
    {
        return rex::getRequest()->isSecure();
    }

    /**
     * Returns the session namespace for the current http request.
     *
     * @return string
     */
    public static function getSessionNamespace()
    {
        // separate backend from frontend namespace,
        // so we can e.g. clear the backend session without
        // logging out the users from the frontend
        $suffix = rex::isBackend() ? '_backend' : '';
        return rex::getProperty('instname') . $suffix;
    }
}
