<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Tab navigation of a page, with the subnavigation of the active tab below.
 *
 * ```php
 * echo new Tabs([
 *     new TabsItem('Overview', Url::backendPage('my_addon/overview'), active: true),
 *     new TabsItem('Settings', Url::backendPage('my_addon/settings')),
 * ], attributes: new HtmlAttributes(['aria-label' => 'My addon']))->render();
 * ```
 *
 * The items of the backend {@see Navigation} become items with {@see TabsItem::fromNavigation()}.
 */
final readonly class Tabs implements Renderable
{
    use RendersView;

    /**
     * @param list<TabsItem> $items
     * @param list<TabsItem> $endItems items aligned at the end
     * @param HtmlAttributes $attributes further attributes of the `<nav>`, e.g. an `id` or an `aria-label` in place of
     *                                   "Subpages"
     */
    public function __construct(
        public array $items,
        public array $endItems = [],
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
