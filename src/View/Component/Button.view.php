<?php

use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonVariant;
use Redaxo\Core\View\Component\Icon;
use Redaxo\Core\View\Html;

return static function (Button $button): void {
    $attributes = $button->attributes->with([
        'class' => ['rex-button'],
        'data-variant' => ButtonVariant::Secondary === $button->variant ? null : $button->variant->value,
        'data-size' => $button->small ? 'xs' : null,
        'href' => $button->url,
        'type' => null === $button->url ? $button->type : null,
    ]);
    $tag = null === $button->url ? 'button' : 'a';
    $icon = null === $button->icon ? '' : new Icon($button->icon)->render();
    $label = $button->hiddenLabel ? '<span class="sr-only">' . (string) Html::from($button->label) . '</span>' : Html::from($button->label) ?>
    <<?= $tag ?> <?= $attributes ?>><?= $icon ?><?= $label ?></<?= $tag ?>>
<?php };
