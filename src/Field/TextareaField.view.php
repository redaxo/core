<?php

use Redaxo\Core\Field\TextareaField;

use function Redaxo\Core\View\escape;

return static function (TextareaField $field): void {
    $binding = $field->binding;
    ?>
    <textarea <?= $field->attributes->with([
        'class' => ['form-control'],
        'name' => $binding->name,
        'id' => $binding->name,
        'rows' => $field->rows,
        'required' => $field->required,
    ]) ?>><?= escape((string) $binding->value) ?></textarea>
<?php };
