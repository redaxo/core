<?php

use Redaxo\Core\View\Component\Badge;
use Redaxo\Core\View\Component\BadgeVariant;
use Redaxo\Core\View\Html;

return static function (Badge $badge): void { ?>
    <span <?= $badge->attributes->with(['class' => ['rex-badge'], 'data-variant' => BadgeVariant::Neutral === $badge->variant ? null : $badge->variant->value]) ?>><?= Html::from($badge->label) ?></span>
<?php };
