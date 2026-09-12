<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Util\Type;

use function sprintf;

/**
 * Class for generating and validating csrf tokens.
 */
final readonly class CsrfToken
{
    public const string PARAM = '_csrf_token';

    private function __construct(
        public string $id,
    ) {}

    public static function factory(string $tokenId): self
    {
        return new self($tokenId);
    }

    public function getValue(): string
    {
        $tokens = self::getTokens();

        if (isset($tokens[$this->id])) {
            return $tokens[$this->id];
        }

        $token = self::generateToken();
        $tokens[$this->id] = $token;
        Session::start()->set(self::getSessionKey(), $tokens);

        return $token;
    }

    public function getHiddenField(): string
    {
        return sprintf('<input type="hidden" name="%s" value="%s"/>', self::PARAM, $this->getValue());
    }

    /**
     * Returns an array containing the `_csrf_token` param.
     *
     * @return array<self::PARAM, string>
     */
    public function getUrlParams(): array
    {
        return [self::PARAM => $this->getValue()];
    }

    public function isValid(): bool
    {
        $tokens = self::getTokens();

        if (!isset($tokens[$this->id])) {
            return false;
        }

        $token = Request::request(self::PARAM, 'string');

        return hash_equals($tokens[$this->id], $token);
    }

    public function remove(): void
    {
        $tokens = self::getTokens();

        if (!isset($tokens[$this->id])) {
            return;
        }

        unset($tokens[$this->id]);

        Session::start()->set(self::getSessionKey(), $tokens);
    }

    public static function removeAll(): void
    {
        $session = Session::start();

        $session->remove(self::getBaseSessionKey());
        $session->remove(self::getBaseSessionKey() . '_https');
    }

    /** @return array<string, string> */
    private static function getTokens(): array
    {
        /** @var array<string, string> */
        return Type::array(Session::start()->get(self::getSessionKey(), []));
    }

    private static function getSessionKey(): string
    {
        // use separate tokens for http/https
        // https://symfony.com/blog/cve-2017-16653-csrf-protection-does-not-use-different-tokens-for-http-and-https
        $suffix = Request::isHttps() ? '_https' : '';

        return self::getBaseSessionKey() . $suffix;
    }

    private static function getBaseSessionKey(): string
    {
        return 'csrf_tokens_' . Core::getEnvironment()->value;
    }

    private static function generateToken(): string
    {
        $bytes = random_bytes(32);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
