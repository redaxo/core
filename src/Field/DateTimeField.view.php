<?php

use Redaxo\Core\Field\DateTimeField;

return static function (DateTimeField $field): void {
    $binding = $field->binding;
    ?>
    <input <?= $field->attributes->with([
        'class' => ['form-control'],
        'type' => 'datetime-local',
        'name' => $binding->name,
        'id' => $binding->name,
        'value' => $field->displayValue(),
        'required' => $field->required,
    ]) ?>>
<?php };
