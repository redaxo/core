<?php

use Redaxo\Core\Field\FieldGroup;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (FieldGroup $group): void {
    $field = $group->field;
    $binding = $field->binding;
    ?>
    <dl <?= new HtmlAttributes(['class' => [
        'rex-form-group',
        'form-group',
        'has-error' => null !== $binding->error,
        'rex-is-required' => $field->required,
    ]]) ?>>
        <?php if ('' !== $field->label): ?>
            <dt><label for="<?= escape($binding->name) ?>"><?= escape($field->label) ?></label></dt>
        <?php endif ?>
        <dd>
            <?= $field->renderInput() ?>
            <?php if (null !== $field->note): ?>
                <p class="help-block rex-note"><?= escape($field->note) ?></p>
            <?php endif ?>
            <?php if (null !== $binding->error): ?>
                <p class="rex-form-error"><?= escape($binding->error) ?></p>
            <?php endif ?>
        </dd>
    </dl>
<?php };
