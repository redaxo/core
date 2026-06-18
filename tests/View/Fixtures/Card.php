<?php

namespace Redaxo\Core\Tests\View\Fixtures;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Fixture demonstrating the "slot" pattern via typed optional {@see Renderable} properties:
 *
 * - `$title`  — required plain-text config
 * - `$body`   — optional content slot (a Renderable) with an inline default defined in the view
 * - `$footer` — optional content slot (string is escaped, Renderable rendered), omitted when absent
 *
 * Callers fill the slots with named arguments (`body: Html::capture(...)` or a sub-component), so
 * the set of slots, which are required and their types are all statically checked — no magic string
 * slot names.
 *
 * @internal
 */
final class Card implements Renderable
{
    use RendersView;

    public function __construct(
        public string $title,
        public ?Renderable $body = null,
        public string|Renderable|null $footer = null,
    ) {}
}
