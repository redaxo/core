<?php

use Redaxo\Core\View\Component\Alert;
use Redaxo\Core\View\Html;

return static function (Alert $alert): void { ?>
    <div <?= $alert->attributes->with(['class' => ['alert', 'alert-' . $alert->type->value]]) ?>><?= Html::from($alert->message) ?></div>
<?php };
