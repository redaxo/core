<?php

use Redaxo\Core\View\Component\Alert;
use Redaxo\Core\View\Component\AlertType;
use Redaxo\Core\View\Html;

return static function (Alert $alert): void { ?>
    <div <?= $alert->attributes->with([
        'class' => ['rex-alert'],
        'data-variant' => $alert->type->value,
        // announced by screen readers: errors and warnings right away, other messages when the reader is idle
        'role' => in_array($alert->type, [AlertType::Error, AlertType::Warning], true) ? 'alert' : 'status',
    ]) ?>><?= Html::from($alert->message) ?></div>
<?php };
