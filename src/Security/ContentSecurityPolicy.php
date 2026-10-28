<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Response;

use function in_array;

/**
 * Content-Security-Policy of the backend pages.
 *
 * Scripts and styles must come from the backend's own origin or carry the nonce of the request
 * ({@see Response::getNonce()}); inline event handlers and style attributes are blocked. Addons extend the policy of
 * the current request, e.g. for an external map service:
 *
 *     ContentSecurityPolicy::backend()->add('img-src', 'https://tile.openstreetmap.org');
 */
final class ContentSecurityPolicy
{
    /**
     * Directives a fetch directive falls back to while it is not set, in order (CSP level 3); a directive added later
     * starts with the sources it had by that fallback, so adding a source only widens the policy.
     */
    private const array FALLBACKS = [
        'script-src-elem' => ['script-src', 'default-src'],
        'script-src-attr' => ['script-src', 'default-src'],
        'style-src-elem' => ['style-src', 'default-src'],
        'style-src-attr' => ['style-src', 'default-src'],
        'worker-src' => ['child-src', 'script-src', 'default-src'],
        'frame-src' => ['child-src', 'default-src'],
        'child-src' => ['default-src'],
        'script-src' => ['default-src'],
        'style-src' => ['default-src'],
        'connect-src' => ['default-src'],
        'font-src' => ['default-src'],
        'img-src' => ['default-src'],
        'manifest-src' => ['default-src'],
        'media-src' => ['default-src'],
        'object-src' => ['default-src'],
    ];

    private static ?self $backend = null;

    /** @param array<string, list<string>> $directives sources per directive, e.g. `['img-src' => ["'self'"]]` */
    public function __construct(
        private array $directives = [],
    ) {}

    /** Returns the policy of the current backend request. */
    public static function backend(): self
    {
        if (null === self::$backend) {
            $nonce = "'nonce-" . Response::getNonce() . "'";
            self::$backend = new self([
                'default-src' => ["'self'"],
                'script-src' => ["'self'", $nonce],
                'style-src' => ["'self'", $nonce],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'frame-src' => ["'self'"],
                'object-src' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'frame-ancestors' => ["'self'"],
            ]);
        }

        return self::$backend;
    }

    /**
     * Allows further sources for a directive, e.g. `add('frame-src', 'https://example.org')`. A directive that is not
     * set yet starts with the sources of its fallback (e.g. `default-src`), and `'unsafe-inline'` replaces the nonces
     * and hashes of the directive, as browsers would ignore it next to them.
     *
     * @throws InvalidArgumentException for a directive or source that would break out of its place in the header
     */
    public function add(string $directive, string ...$sources): self
    {
        if (!preg_match('/^[a-z-]+$/', $directive)) {
            throw new InvalidArgumentException('Invalid Content-Security-Policy directive "' . $directive . '".');
        }
        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = $this->inherited($directive);
        }
        foreach ($sources as $source) {
            if ('' === $source || preg_match('/[\s;,]/', $source)) {
                throw new InvalidArgumentException('Invalid Content-Security-Policy source "' . $source . '".');
            }
            // a single 'none' would block the added sources
            $current = array_values(array_diff($this->directives[$directive] ?? [], ["'none'"]));
            // browsers ignore 'unsafe-inline' next to a nonce or hash, so these give way to it
            if ("'unsafe-inline'" === $source) {
                $current = array_values(array_filter($current, static fn (string $item): bool => !preg_match("/^'(nonce|sha256|sha384|sha512)-/", $item)));
            }
            if (!in_array($source, $current, true)) {
                $current[] = $source;
            }
            $this->directives[$directive] = $current;
        }

        return $this;
    }

    /**
     * Sources a directive that is not set gets by its fallback.
     *
     * @return list<string>
     */
    private function inherited(string $directive): array
    {
        foreach (self::FALLBACKS[$directive] ?? [] as $fallback) {
            if (isset($this->directives[$fallback])) {
                return $this->directives[$fallback];
            }
        }

        return [];
    }

    /** @return list<string> */
    public function get(string $directive): array
    {
        return $this->directives[$directive] ?? [];
    }

    public function toString(): string
    {
        $parts = [];
        foreach ($this->directives as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }
}
