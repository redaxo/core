<?php

use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\Icon;
use Redaxo\Core\View\Html;

return static function (Button $button): void {
    $attributes = $button->attributes->with([
        'class' => ['btn', ...(null === $button->variant ? [] : ['btn-' . $button->variant->value]), 'btn-xs' => $button->small],
        'href' => $button->url,
        'type' => null === $button->url ? $button->type : null,
    ]);
    $tag = null === $button->url ? 'button' : 'a';
    $icon = null === $button->icon ? '' : new Icon($button->icon)->render();
    $label = $button->hiddenLabel ? '<span class="sr-only">' . (string) Html::from($button->label) . '</span>' : Html::from($button->label) ?>
    <<?= $tag ?> <?= $attributes ?>><?= $icon ?><?= $label ?></<?= $tag ?>>
<?php };
