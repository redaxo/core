<?php

use Redaxo\Core\View\Component\ButtonGroup;
use Redaxo\Core\View\HtmlAttributes;

return static function (ButtonGroup $group): void { ?>
    <div <?= new HtmlAttributes(['class' => ['rex-button-group'], 'data-orientation' => $group->vertical ? 'vertical' : null, 'data-size' => $group->small ? 'xs' : null]) ?>>
        <?php foreach ($group->buttons as $button): ?>
            <?= $button->render() ?>
        <?php endforeach ?>
    </div>
<?php };
