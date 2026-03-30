<?php

/**
 * Class for generating and validating csrf tokens.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_csrf_token
{
    use rex_factory_trait;

    public const PARAM = '_csrf_token';

    private function __construct(
        private string $id,
    ) {}

    /**
     * @param string $tokenId
     *
     * @return static
     */
    public static function factory($tokenId)
    {
        $class = static::getFactoryClass();

        return new $class($tokenId);
    }

    /** @return string */
    public function getId()
    {
        return $this->id;
    }

    /** @return string */
    public function getValue()
    {
        $tokens = self::getTokens();

        if (isset($tokens[$this->id])) {
            return $tokens[$this->id];
        }

        $token = self::generateToken();
        $tokens[$this->id] = $token;
        rex_set_session(self::getSessionKey(), $tokens);

        return $token;
    }

    /** @return string */
    public function getHiddenField()
    {
        return sprintf('<input type="hidden" name="%s" value="%s"/>', self::PARAM, $this->getValue());
    }

    /**
     * Returns an array containing the `_csrf_token` param.
     *
     * @return array<self::PARAM, string>
     */
    public function getUrlParams()
    {
        return [self::PARAM => $this->getValue()];
    }

    /** @return bool */
    public function isValid()
    {
        $tokens = self::getTokens();

        if (!isset($tokens[$this->id])) {
            return false;
        }

        $token = rex_request(self::PARAM, 'string');

        return hash_equals($tokens[$this->id], $token);
    }

    /** @return void */
    public function remove()
    {
        $tokens = self::getTokens();

        if (!isset($tokens[$this->id])) {
            return;
        }

        unset($tokens[$this->id]);

        rex_set_session(self::getSessionKey(), $tokens);
    }

    /** @return void */
    public static function removeAll()
    {
        rex_login::startSession();

        rex_unset_session(self::getBaseSessionKey());
        rex_unset_session(self::getBaseSessionKey() . '_https');
    }

    /** @return array<string, string> */
    private static function getTokens()
    {
        rex_login::startSession();

        return rex_session(self::getSessionKey(), 'array');
    }

    /** @return string */
    private static function getSessionKey()
    {
        // use separate tokens for http/https
        // https://symfony.com/blog/cve-2017-16653-csrf-protection-does-not-use-different-tokens-for-http-and-https
        $suffix = rex_request::isHttps() ? '_https' : '';

        return self::getBaseSessionKey() . $suffix;
    }

    /** @return string */
    private static function getBaseSessionKey()
    {
        return 'csrf_tokens_' . rex::getEnvironment();
    }

    /** @return string */
    private static function generateToken()
    {
        $bytes = random_bytes(32);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
