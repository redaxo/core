<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Tab navigation of a page, with the subnavigation of the active tab below.
 *
 * Items are arrays as produced by {@see Navigation}: `title` (HTML), and optionally `href`,
 * `active`, `icon`, `itemClasses`, `itemAttr`, `linkClasses`, `linkAttr` and `children` (the subnavigation items of
 * an active item).
 *
 * @psalm-type Item = array<string, mixed>
 */
final class Tabs implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<Item> */
        public array $items,
        /** @var list<Item> Items aligned at the end */
        public array $endItems = [],
        public ?string $id = null,
    ) {}
}
