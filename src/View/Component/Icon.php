<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Icon of the REDAXO icon set, e.g. `new Icon('add')` for `rex-icon-add`. */
final class Icon implements Renderable
{
    use RendersView;

    public function __construct(
        public string $name,
        /** Accessible label; without it the icon is decorative and hidden from assistive technology */
        public ?string $label = null,
    ) {}
}
