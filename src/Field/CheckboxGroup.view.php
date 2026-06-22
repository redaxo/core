<?php

use Redaxo\Core\Field\CheckboxGroup;

use function Redaxo\Core\View\escape;

return static function (CheckboxGroup $group): void {
    $field = $group->field;
    ?>
    <div class="checkbox">
        <label><?= $field->renderInput() ?> <?= escape($field->label) ?></label>
        <?php if (null !== $field->note): ?>
            <p class="help-block rex-note"><?= escape($field->note) ?></p>
        <?php endif ?>
    </div>
<?php };
