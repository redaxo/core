<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Main content frame of a backend page: the content area, optionally with a sidebar.
 *
 * Accepts any {@see Renderable} (a component, or {@see Html} for trusted markup).
 * The sidebar is only shown when there is content.
 */
final class MainContent implements Renderable
{
    use RendersView;

    public function __construct(
        public ?Renderable $content = null,
        public ?Renderable $sidebar = null,
    ) {}
}
