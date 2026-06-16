<?php

use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Html;

return static function (PageHeader $pageHeader): void { ?>
<header class="rex-page-header">
    <div class="page-header">
        <h1><?= Html::from($pageHeader->heading) ?>
            <?php if (null !== $pageHeader->subheading): ?>
                <small><?= Html::from($pageHeader->subheading) ?></small>
            <?php endif ?>
        </h1>
    </div>
    <?php if (null !== $pageHeader->subtitle): ?>
        <?= Html::from($pageHeader->subtitle) ?>
    <?php endif ?>
</header>
<?php };
