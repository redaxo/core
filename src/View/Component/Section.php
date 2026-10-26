<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * A titled box of content, the main building block of backend pages.
 *
 * Plain strings are escaped, markup is passed as {@see Html} or another {@see Renderable}.
 */
final class Section implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $title = '',
        /** Content with padding */
        public string|Renderable $body = '',
        /** Content without padding, e.g. a table or a list */
        public string|Renderable $content = '',
        public string|Renderable $footer = '',
        /** Controls on the right of the header, e.g. a search field */
        public string|Renderable $options = '',
        /** Further header content below the title, e.g. tabs */
        public string|Renderable $header = '',
        public SectionVariant $variant = SectionVariant::Default,
        /** Whether a click on the header collapses the content */
        public bool $collapsible = false,
        public bool $collapsed = false,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
