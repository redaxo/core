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
     * Allows further sources for a directive, e.g. `add('frame-src', 'https://example.org')`.
     *
     * @throws InvalidArgumentException for a directive or source that would break out of its place in the header
     */
    public function add(string $directive, string ...$sources): self
    {
        if (!preg_match('/^[a-z-]+$/', $directive)) {
            throw new InvalidArgumentException('Invalid Content-Security-Policy directive "' . $directive . '".');
        }
        foreach ($sources as $source) {
            if ('' === $source || preg_match('/[\s;,]/', $source)) {
                throw new InvalidArgumentException('Invalid Content-Security-Policy source "' . $source . '".');
            }
            // a single 'none' would block the added sources
            $current = array_values(array_diff($this->directives[$directive] ?? [], ["'none'"]));
            if (!in_array($source, $current, true)) {
                $current[] = $source;
            }
            $this->directives[$directive] = $current;
        }

        return $this;
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
