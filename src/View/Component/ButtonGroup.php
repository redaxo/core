<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Buttons joined to one control. */
final class ButtonGroup implements Renderable
{
    use RendersView;

    public function __construct(
        /** @var list<Renderable> */
        public array $buttons,
        public bool $vertical = false,
        public bool $small = false,
    ) {}
}
