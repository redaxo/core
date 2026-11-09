<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Bar of controls that collapses behind a toggle on small screens. */
final class Toolbar implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $content,
        public string|Renderable $brand = '',
        /** Dark variant */
        public bool $inverse = false,
        /** Further attributes of the `<nav>`, e.g. an `aria-label` when the page has several navigations */
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
