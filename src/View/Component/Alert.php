<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Message box, e.g. the result of an action. */
final class Alert implements Renderable
{
    use RendersView;

    public function __construct(
        public string|Renderable $message,
        public AlertType $type = AlertType::Info,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {}
}
