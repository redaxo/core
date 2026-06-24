<?php

use Redaxo\Core\Field\ChoiceField;

return static function (ChoiceField $field): void {
    if ($field->expanded) {
        echo $field->renderExpandedItems();

        return;
    }

    $binding = $field->binding;
    ?>
    <select <?= $field->attributes->with([
        'class' => ['form-control', 'selectpicker'],
        'name' => $field->multiple ? $binding->name . '[]' : $binding->name,
        'id' => $binding->name,
        'multiple' => $field->multiple,
        'required' => $field->required,
    ]) ?>><?= $field->renderOptions() ?></select>
<?php };
