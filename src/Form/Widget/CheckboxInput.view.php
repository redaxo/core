<?php

use Redaxo\Core\Form\Widget\CheckboxInput;
use Redaxo\Core\View\Html;

return static function (CheckboxInput $checkbox): void { ?>
    <div class="rex-check">
        <label>
            <input <?= $checkbox->attributes->with([
                'type' => 'checkbox',
                'name' => $checkbox->name,
                'id' => $checkbox->id,
                'value' => $checkbox->value,
                'checked' => $checkbox->checked,
                'required' => $checkbox->required,
                'disabled' => $checkbox->disabled,
            ]) ?>>
            <?= Html::from($checkbox->label) ?>
        </label>
    </div>
<?php };
