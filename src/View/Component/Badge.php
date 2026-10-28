<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Short marker next to other content, e.g. a role or a state. */
final class Badge implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $label,
        public BadgeVariant $variant = BadgeVariant::Neutral,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
