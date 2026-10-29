<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Form\ButtonStyle;
use Redaxo\Core\Form\Widget\Button;

use function Redaxo\Core\View\escape;

return static function (Button $button): void {
    $accesskey = null === $button->accesskey ? null : Accesskey::get($button->accesskey) ?>
    <button <?= $button->attributes->with([
        'class' => ['rex-button'],
        'data-variant' => match ($button->style) {
            ButtonStyle::Primary => 'success',
            ButtonStyle::Secondary => null,
            ButtonStyle::Danger => 'danger',
        },
        // forms.js clicks this button on cmd/ctrl + enter, to save without leaving the form
        'data-rex-submit-shortcut' => ButtonStyle::Primary === $button->style && 'apply' === $button->value,
        'type' => 'submit',
        'name' => $button->name,
        'value' => $button->value,
        'accesskey' => $accesskey,
        'title' => null === $accesskey ? null : $button->label . ' [' . $accesskey . ']',
    ]) ?>><?= escape($button->label) ?></button>
<?php };
