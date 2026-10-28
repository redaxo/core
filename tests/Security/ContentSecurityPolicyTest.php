<?php

namespace Redaxo\Core\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\ContentSecurityPolicy;

/** @internal */
final class ContentSecurityPolicyTest extends TestCase
{
    public function testBackendAllowsScriptsAndStylesOnlyWithNonce(): void
    {
        $nonce = "'nonce-" . Response::getNonce() . "'";
        $policy = ContentSecurityPolicy::backend();

        self::assertSame(["'self'", $nonce], $policy->get('script-src'));
        self::assertSame(["'self'", $nonce], $policy->get('style-src'));
        self::assertStringNotContainsString('unsafe-inline', $policy->toString());
        self::assertSame($policy, ContentSecurityPolicy::backend(), 'addons extend the policy of the current request');
    }

    public function testAdd(): void
    {
        $policy = new ContentSecurityPolicy(['img-src' => ["'self'"], 'object-src' => ["'none'"]]);
        $policy->add('img-src', 'https://example.org', "'self'");
        $policy->add('object-src', 'https://example.org');
        $policy->add('frame-src', 'https://example.org');

        self::assertSame("img-src 'self' https://example.org; object-src https://example.org; frame-src https://example.org", $policy->toString());
    }

    public function testAddStartsWithFallback(): void
    {
        $policy = new ContentSecurityPolicy(['default-src' => ["'self'"], 'script-src' => ["'self'", "'nonce-abc'"]]);
        $policy->add('media-src', 'https://example.org');
        $policy->add('worker-src', 'blob:');
        $policy->add('form-action', 'https://example.org');

        self::assertSame(["'self'", 'https://example.org'], $policy->get('media-src'), 'media-src falls back to default-src');
        self::assertSame(["'self'", "'nonce-abc'", 'blob:'], $policy->get('worker-src'), 'worker-src falls back to script-src');
        self::assertSame(['https://example.org'], $policy->get('form-action'), 'form-action has no fallback');
    }

    #[DataProvider('provideAddRejectsInjection')]
    public function testAddRejectsInjection(string $directive, string $source): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentSecurityPolicy()->add($directive, $source);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideAddRejectsInjection(): iterable
    {
        yield 'source with another directive' => ['img-src', "https://example.org; script-src 'unsafe-inline'"];
        yield 'source with whitespace' => ['img-src', "https://example.org 'unsafe-eval'"];
        yield 'source with comma' => ['img-src', 'https://example.org,https://evil.example'];
        yield 'empty source' => ['img-src', ''];
        yield 'directive with source' => ["script-src 'unsafe-inline'", 'https://example.org'];
    }
}
