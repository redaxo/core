<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Heading of a backend page, optionally followed by its navigation (see {@see Tabs}). */
final class PageHeader implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $heading,
        public string|Renderable $subheading = '',
        public string|Renderable $navigation = '',
    ) {}
}
