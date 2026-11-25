<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Heading of a backend page, optionally followed by its navigation (see {@see Tabs}).
 *
 * With an eyebrow the heading reads like a path, e.g. "System • Settings"; the meta text stands on the right, e.g. the
 * versions on the system page.
 */
final class PageHeader implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $heading,
        public string|Renderable $subheading = '',
        public string|Renderable $navigation = '',
        /** The section the page belongs to, before the heading */
        public string|Renderable $eyebrow = '',
        public string|Renderable $meta = '',
    ) {}
}
