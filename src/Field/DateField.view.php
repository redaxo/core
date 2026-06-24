<?php

use Redaxo\Core\Field\DateField;

return static function (DateField $field): void {
    $binding = $field->binding;
    ?>
    <input <?= $field->attributes->with([
        'class' => ['form-control'],
        'type' => 'date',
        'name' => $binding->name,
        'id' => $binding->name,
        'value' => $field->displayValue(),
        'required' => $field->required,
    ]) ?>>
<?php };
