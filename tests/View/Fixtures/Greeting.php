<?php

namespace Redaxo\Core\Tests\View\Fixtures;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Fixture component for the view system tests.
 *
 * Plain promoted properties — no escaping logic here. `$name` is plain text, `$message` may be a
 * plain string or any {@see Renderable} (markup or a sub-component); the view normalizes each (see
 * Greeting.view.php).
 *
 * @internal
 */
final class Greeting implements Renderable
{
    use RendersView;

    public function __construct(
        public string $name,
        public string|Renderable $message,
    ) {}
}
