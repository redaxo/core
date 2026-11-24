<?php

use Redaxo\Core\View\Component\Icon;
use Redaxo\Core\View\HtmlAttributes;

return static function (Icon $icon): void { ?>
    <i <?= new HtmlAttributes([
        'class' => ['rex-icon', 'rex-icon-' . $icon->name],
        'aria-hidden' => null === $icon->label ? 'true' : null,
        'role' => null === $icon->label ? null : 'img',
        'aria-label' => $icon->label,
    ]) ?>></i>
<?php };
