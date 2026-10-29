<?php

use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\Form\Widget\Widget;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

return static function (FieldRow $row): void {
    $labelFor = $row->labelFor();
    // a label without target belongs to a group of controls (e.g. checkboxes), which a fieldset with legend names
    $group = null === $labelFor && null !== $row->label;
    $id = $labelFor ?? 'rex-field-' . bin2hex(random_bytes(4));
    $noteId = null === $row->note ? null : $id . '-note';
    $errorId = null === $row->error ? null : $id . '-error';
    $description = [
        'aria-describedby' => implode(' ', array_filter([$errorId, $noteId])) ?: null,
        'aria-invalid' => null === $row->error ? null : 'true',
    ];

    $widget = $row->widget;
    if (!$group && $widget instanceof Widget) {
        $widget = clone $widget;
        $widget->attributes = $widget->attributes->with($description);
    }

    $tag = $group ? 'fieldset' : 'div';
    $labelTag = $group ? 'legend' : 'label' ?>
    <<?= $tag ?> <?= new HtmlAttributes([
        'class' => ['rex-field'],
        'data-layout' => $row->vertical ? 'vertical' : null,
        ...($group ? $description : []),
    ]) ?>>
        <?php if (null !== $row->label): ?>
            <<?= $labelTag ?> <?= new HtmlAttributes(['class' => 'rex-field-label', 'for' => $labelFor]) ?>>
                <?= Html::from($row->label) ?>
                <?php if ($row->required): ?><span class="rex-field-required" aria-hidden="true">*</span><?php endif ?>
            </<?= $labelTag ?>>
        <?php endif ?>
        <div class="rex-field-control">
            <?= $widget->render() ?>
            <?php if (null !== $row->note): ?>
                <p class="rex-field-note" id="<?= $noteId ?>"><?= Html::from($row->note) ?></p>
            <?php endif ?>
            <?php if (null !== $row->error): ?>
                <p class="rex-field-error" id="<?= $errorId ?>"><?= Html::from($row->error) ?></p>
            <?php endif ?>
        </div>
    </<?= $tag ?>>
<?php };
