<?php

namespace Redaxo\Core\Field;

/**
 * Base for fields backed by a single `<input>` control.
 *
 * Subclasses define how the stored value is formatted for display ({@see self::displayValue()}); the
 * `<input>` markup itself (its `type`, classes and how the presentational `$attributes` are merged in)
 * lives in the co-located `*.view.php`, so it stays overridable.
 */
abstract class InputField extends Field implements ProvidesColumn
{
    /** The bound value formatted for the `value` attribute. */
    public function displayValue(): string
    {
        return (string) $this->binding->value;
    }
}
