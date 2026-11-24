<?php

namespace Redaxo\Core\Form\Widget;

use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

/** A form control that can be rendered standalone or within a {@see FieldRow}. */
interface Widget extends Renderable
{
    /**
     * Additional attributes of the control (e.g. the `aria-describedby` a {@see FieldRow} adds); widgets of several
     * controls put them on their wrapping element.
     */
    public HtmlAttributes $attributes { get; set; }

    /** Id of the element a label in front of the widget points to, `null` if there is none. */
    public function labelTarget(): ?string;
}
