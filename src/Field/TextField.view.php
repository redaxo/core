<?php

use Redaxo\Core\Field\TextField;

return static function (TextField $field): void {
    $binding = $field->binding;
    ?>
    <input <?= $field->attributes->with([
        'class' => ['form-control'],
        'type' => 'text',
        'name' => $binding->name,
        'id' => $binding->name,
        'value' => (string) $binding->value,
        'maxlength' => $field->maxLength,
        'required' => $field->required,
    ]) ?>>
<?php };
