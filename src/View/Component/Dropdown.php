<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Button opening a menu of links.
 *
 * Items: `title` (a plain string is escaped), and optionally `href`, `active` and `attributes` of the list item.
 */
final class Dropdown implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<array{title: string|Renderable, href?: string, active?: bool, attributes?: HtmlAttributes}> */
        public array $items,
        /** Highlighted part of the toggle, e.g. the current choice */
        public string|Renderable $label = '',
        /** Part of the toggle before the label */
        public string|Renderable $prefix = '',
        public string|Renderable $header = '',
        public string|Renderable $footer = '',
        /** Aligns the menu with the end of the toggle */
        public bool $alignEnd = false,
        /** Full width */
        public bool $block = false,
        public bool $disabled = false,
        /** Toggle styled as plain link instead of a button, e.g. within a toolbar */
        public bool $link = false,
        /** Without the wrapping element, when the surrounding element (e.g. a button group) takes its role */
        public bool $wrapped = true,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
