<?php

use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (SelectInput $select): void { ?>
    <select <?= $select->attributes->with([
        'class' => ['form-control'],
        'name' => $select->multiple ? $select->name . '[]' : $select->name,
        'id' => $select->id,
        'multiple' => $select->multiple,
        'required' => $select->required,
        'disabled' => $select->disabled,
        'data-rex-select' => $select->multiple || $select->search,
        'data-rex-select-search' => $select->search,
    ]) ?>>
        <?php if (null !== $select->placeholder && !$select->multiple): ?>
            <option value=""><?= escape(Translatable::text($select->placeholder)) ?></option>
        <?php endif ?>
        <?php foreach ($select->groups() as $group): ?>
            <?php if (null !== $group['label']): ?><optgroup label="<?= escape($group['label']) ?>"><?php endif ?>
            <?php foreach ($group['choices'] as $value => $label): ?>
                <option <?= new HtmlAttributes(['value' => (string) $value, 'selected' => $select->isSelected($value)]) ?>><?= escape(Translatable::text($label)) ?></option>
            <?php endforeach ?>
            <?php if (null !== $group['label']): ?></optgroup><?php endif ?>
        <?php endforeach ?>
    </select>
<?php };
