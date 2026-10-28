<?php

use Redaxo\Core\Form\Widget\ChoiceList;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (ChoiceList $list): void {
    $type = $list->multiple ? 'checkbox' : 'radio' ?>
    <div <?= $list->attributes->with(['id' => $list->id, 'class' => ['rex-choice-list']]) ?>>
        <?php foreach ($list->groups() as $group): ?>
            <?php if (null !== $group['label']): ?><fieldset class="rex-choice-group"><legend><?= escape($group['label']) ?></legend><?php endif ?>
            <?php foreach ($group['choices'] as $value => $label): ?>
                <div class="rex-check"<?= $list->inline ? ' data-layout="inline"' : '' ?>>
                    <label>
                        <input <?= new HtmlAttributes([
                            'type' => $type,
                            'name' => $list->multiple ? $list->name . '[]' : $list->name,
                            'id' => $list->optionId($value),
                            'value' => (string) $value,
                            'checked' => $list->isSelected($value),
                            'disabled' => $list->disabled,
                        ]) ?>>
                        <?= escape(Translatable::text($label)) ?>
                    </label>
                </div>
            <?php endforeach ?>
            <?php if (null !== $group['label']): ?></fieldset><?php endif ?>
        <?php endforeach ?>
    </div>
<?php };
