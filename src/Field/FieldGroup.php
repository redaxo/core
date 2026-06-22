<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;
use Redaxo\Core\View\ViewResolver;

/**
 * The default form-group chrome around a field: label, control, note and error, in REDAXO's
 * `rex-form-group` markup.
 *
 * A separate, themeable component: register an override for its view via
 * {@see ViewResolver::override()} to restyle every field group at once, without
 * touching the fields. Field types needing different chrome (e.g. {@see CheckboxField}) return their
 * own group component from {@see Field::render()}.
 */
final class FieldGroup implements Renderable
{
    use RendersView;

    public function __construct(
        public Field $field,
    ) {}
}
