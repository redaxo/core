<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Util\Type;

use function assert;
use function count;
use function sprintf;
use function strlen;

/**
 * Class for generating and validating csrf tokens.
 *
 * The secret stored in the session is never output directly: every output masks it with a fresh random key, so the
 * value differs on every rendering and cannot be recovered via compression side channels like BREACH.
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

        if (!isset($tokens[$this->id])) {
            $tokens[$this->id] = self::generateToken();
            Session::start()->set(self::getSessionKey(), $tokens);
        }

        return self::mask($tokens[$this->id]);
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

        $token = self::unmask(Request::request(self::PARAM, 'string'));

        return null !== $token && hash_equals($tokens[$this->id], $token);
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

    /** @return array<string, non-empty-string> */
    private static function getTokens(): array
    {
        /** @var array<string, non-empty-string> */
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

    /** @return non-empty-string */
    private static function generateToken(): string
    {
        $token = self::encode(random_bytes(32));
        assert('' !== $token);

        return $token;
    }

    /** @param non-empty-string $token */
    private static function mask(string $token): string
    {
        $key = random_bytes(strlen($token));

        return self::encode($key) . '.' . self::encode($key ^ $token);
    }

    private static function unmask(string $value): ?string
    {
        $parts = explode('.', $value);
        if (2 !== count($parts)) {
            return null;
        }

        $key = self::decode($parts[0]);
        $masked = self::decode($parts[1]);
        if (null === $key || null === $masked || strlen($key) !== strlen($masked)) {
            return null;
        }

        return $key ^ $masked;
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $bytes ? null : $bytes;
    }
}
