<?php

namespace Redaxo\Core\Tests\Security;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Security\CsrfToken;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final class CsrfTokenTest extends TestCase
{
    private ?Request $previousRequest;

    #[Override]
    protected function setUp(): void
    {
        $this->previousRequest = Core::hasRequest() ? Core::getRequest() : null;
        Core::setRequest(Request::create('/'));
        CsrfToken::removeAll();
    }

    #[Override]
    protected function tearDown(): void
    {
        CsrfToken::removeAll();
        unset($_REQUEST[CsrfToken::PARAM]);
        Core::setRequest($this->previousRequest);
    }

    public function testValueIsMaskedOnEveryOutput(): void
    {
        $token = CsrfToken::factory('test');

        $value1 = $token->getValue();
        $value2 = $token->getValue();

        self::assertNotSame($value1, $value2);
        self::assertSame(self::unmask($value1), self::unmask($value2));

        $this->setRequestToken($value1);
        self::assertTrue($token->isValid());

        $this->setRequestToken($value2);
        self::assertTrue($token->isValid());
    }

    /** @param callable(string): string $submitted */
    #[DataProvider('provideIsValidRejects')]
    public function testIsValidRejects(callable $submitted): void
    {
        $token = CsrfToken::factory('test');

        $this->setRequestToken($submitted($token->getValue()));

        self::assertFalse($token->isValid());
    }

    /** @return iterable<string, array{callable(string): string}> */
    public static function provideIsValidRejects(): iterable
    {
        yield 'missing' => [static fn (string $value): string => ''];
        yield 'unmasked secret' => [self::unmask(...)];
        yield 'tampered' => [static fn (string $value): string => strrev($value)];
        yield 'invalid base64' => [static fn (string $value): string => str_replace('.', '.*', $value)];
        yield 'too many parts' => [static fn (string $value): string => $value . '.abc'];
        yield 'token of other id' => [static fn (string $value): string => CsrfToken::factory('other')->getValue()];
    }

    private function setRequestToken(string $value): void
    {
        $_REQUEST[CsrfToken::PARAM] = $value;
    }

    private static function unmask(string $value): string
    {
        [$key, $masked] = array_map(
            static fn (string $part): string => base64_decode(strtr($part, '-_', '+/')),
            explode('.', $value),
        );

        return $key ^ $masked;
    }
}
