<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Button, or a link looking like one when a `url` is given. */
final class Button implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $label,
        public ?string $url = null,
        /** Name of an {@see Icon} shown before the label */
        public ?string $icon = null,
        /** `null` for a button without variant styles */
        public ?ButtonVariant $variant = ButtonVariant::Default,
        public bool $small = false,
        /** Shows the icon only, the label stays for assistive technology */
        public bool $hiddenLabel = false,
        /** Type of a `<button>`: `button`, `submit` or `reset` */
        public string $type = 'button',
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
