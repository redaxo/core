<?php

use Redaxo\Core\Field\CheckboxField;

return static function (CheckboxField $field): void {
    $binding = $field->binding;
    ?>
    <input <?= $field->attributes->with([
        'type' => 'checkbox',
        'name' => $binding->name,
        'id' => $binding->name,
        'value' => '1',
        'checked' => (bool) $binding->value,
    ]) ?>>
<?php };
