<?php

use Redaxo\Core\Form\Widget\Button;
use Redaxo\Core\Form\Widget\ButtonToolbar;

return static function (ButtonToolbar $toolbar): void {
    $buttons = $toolbar->buttons;
    if ([] !== $buttons) {
        /** @var Button $first */
        $first = clone ($buttons[0], ['attributes' => $buttons[0]->attributes->with(['class' => ['rex-form-aligned']])]);
        $buttons[0] = $first;
    } ?>
    <div class="rex-button-toolbar">
        <?php foreach ($buttons as $button): ?>
            <?= $button->render() ?>
        <?php endforeach ?>
        <?= $toolbar->cancelLink?->render() ?>
    </div>
<?php };
