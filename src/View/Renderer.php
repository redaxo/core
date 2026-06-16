<?php

namespace Redaxo\Core\View;

use Closure;
use Redaxo\Core\Exception\InvalidArgumentException;
use Throwable;

use function is_file;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function sprintf;

/**
 * Low-level engine for closure-based view files.
 *
 * A view file returns a typed closure and emits nothing on its own — requiring it is inert, it only
 * hands back the closure. The renderer then invokes that closure with the given arguments inside an
 * output buffer and returns the captured markup.
 *
 * @internal component authors render through {@see RendersView}/{@see Renderable}, not this class
 */
final class Renderer
{
    /** @param array<array-key, mixed> $args Arguments spread into the view closure */
    public static function render(string $path, array $args = []): string
    {
        $closure = self::load($path);

        return self::capture(static function () use ($closure, $args): void {
            $closure(...$args);
        });
    }

    /** Runs the closure and returns whatever it emitted, cleaning up the output buffer on a throw. */
    public static function capture(Closure $closure): string
    {
        ob_start();
        try {
            $closure();
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    private static function load(string $path): Closure
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('View file "%s" not found.', $path));
        }

        $closure = require $path;

        if (!$closure instanceof Closure) {
            throw new InvalidArgumentException(sprintf('View file "%s" must return a closure.', $path));
        }

        return $closure;
    }
}
