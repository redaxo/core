<?php

use Redaxo\Core\View\Component\ButtonGroup;
use Redaxo\Core\View\HtmlAttributes;

return static function (ButtonGroup $group): void { ?>
    <div <?= new HtmlAttributes(['class' => [$group->vertical ? 'btn-group-vertical' : 'btn-group', 'btn-group-xs' => $group->small]]) ?>>
        <?php foreach ($group->buttons as $button): ?>
            <?= $button->render() ?>
        <?php endforeach ?>
    </div>
<?php };
