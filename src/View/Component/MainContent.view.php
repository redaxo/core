<?php

use Redaxo\Core\View\Component\MainContent;
use Redaxo\Core\View\Html;

return static function (MainContent $mainContent): void { ?>
<section class="rex-main-frame">
    <?php if (null !== $mainContent->content && null !== $mainContent->sidebar): ?>
    <div class="row">
        <div class="col-lg-8">
            <div id="rex-js-main-content" class="rex-main-content">
                <?= Html::from($mainContent->content) ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div id="rex-js-main-sidebar" class="rex-main-sidebar">
                <?= Html::from($mainContent->sidebar) ?>
            </div>
        </div>
    </div>
    <?php elseif (null !== $mainContent->content): ?>
    <div class="row">
        <div class="col-md-12">
            <div id="rex-js-main-content" class="rex-main-content">
                <?= Html::from($mainContent->content) ?>
            </div>
        </div>
    </div>
    <?php endif ?>
</section>
<?php };
