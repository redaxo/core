<?php

use Redaxo\Core\Form\Widget\Input;

return static function (Input $input): void { ?>
    <input <?= $input->attributes->with([
        'class' => ['rex-input'],
        'type' => $input->type,
        'name' => $input->name,
        'id' => $input->id,
        'value' => $input->value,
        'required' => $input->required,
        'disabled' => $input->disabled,
    ]) ?>>
<?php };
