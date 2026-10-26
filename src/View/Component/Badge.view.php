<?php

use Redaxo\Core\View\Component\Badge;
use Redaxo\Core\View\Html;

return static function (Badge $badge): void { ?>
    <span <?= $badge->attributes->with(['class' => ['label', 'label-' . $badge->variant->value]]) ?>><?= Html::from($badge->label) ?></span>
<?php };
