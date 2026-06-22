<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\View\HtmlAttributes;

/**
 * Base for fields backed by a single `<input>` control.
 *
 * Subclasses declare the input `type` ({@see self::inputType()}) and, if needed, how the stored value
 * is formatted for display ({@see self::displayValue()}). The structural attributes
 * (`name`/`id`/`type`/`value`/`required`) are merged on top of the field's presentational ones via
 * {@see HtmlAttributes::with()}, so they can not be overridden, while `class` is extended.
 */
abstract class InputField extends Field implements ProvidesColumn
{
    /** The HTML input `type`. */
    abstract protected function inputType(): string;

    /** The bound value formatted for the `value` attribute. */
    protected function displayValue(): string
    {
        return (string) $this->binding->value;
    }

    /** The complete attribute set for the `<input>` element. */
    public function inputAttributes(): HtmlAttributes
    {
        return $this->attributes()->with([
            'class' => ['form-control'],
            'type' => $this->inputType(),
            'name' => $this->binding->name,
            'id' => $this->binding->name,
            'value' => $this->displayValue(),
            'required' => $this->required,
        ]);
    }
}
