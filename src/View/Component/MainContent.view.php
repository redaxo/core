<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<section class="rex-main-frame">
    <?php if (isset($this->content) && '' != $this->content && isset($this->sidebar) && '' != $this->sidebar): ?>
    <div class="row">
        <div class="col-lg-8">
            <div id="rex-js-main-content" class="rex-main-content">
                <?= $this->content ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div id="rex-js-main-sidebar" class="rex-main-sidebar">
                <?= $this->sidebar ?>
            </div>
        </div>
    </div>
    <?php elseif (isset($this->content) && '' != $this->content): ?>
    <div class="row">
        <div class="col-md-12">
            <div id="rex-js-main-content" class="rex-main-content">
                <?= $this->content ?>
            </div>
        </div>
    </div>
    <?php endif ?>
</section>
