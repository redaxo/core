<?php

use Redaxo\Core\Form\Widget\Textarea;

use function Redaxo\Core\View\escape;

return static function (Textarea $textarea): void { ?>
    <textarea <?= $textarea->attributes->with([
        'class' => ['rex-input'],
        'name' => $textarea->name,
        'id' => $textarea->id,
        'rows' => $textarea->rows,
        'required' => $textarea->required,
        'disabled' => $textarea->disabled,
    ]) ?>><?= escape((string) $textarea->value) ?></textarea>
<?php };
