<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Group chrome for a {@see CheckboxField}: the control sits inside its `<label>`. */
final class CheckboxGroup implements Renderable
{
    use RendersView;

    public function __construct(
        public CheckboxField $field,
    ) {}
}
