<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Path to the current location, e.g. the category tree of an article. */
final class Breadcrumb implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<array{title: string|Renderable, href?: string}> */
        public array $items,
        /** Leading item, e.g. a link to the root level */
        public string|Renderable $title = '',
        public ?string $id = null,
    ) {}
}
