<?php

use Redaxo\Core\Field\TimeField;

return static function (TimeField $field): void {
    $binding = $field->binding;
    ?>
    <input <?= $field->attributes->with([
        'class' => ['form-control'],
        'type' => 'time',
        'name' => $binding->name,
        'id' => $binding->name,
        'value' => $field->displayValue(),
        'required' => $field->required,
    ]) ?>>
<?php };
