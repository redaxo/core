<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Page header (heading with optional subheading and subtitle).
 *
 * Accepts any {@see Renderable} (a component, or {@see Html} for trusted markup),
 * so callers can pass sub-components directly without rendering them first.
 */
final class PageHeader implements Renderable
{
    use RendersView;

    public function __construct(
        public Renderable $heading,
        public ?Renderable $subheading = null,
        public ?Renderable $subtitle = null,
    ) {}
}
