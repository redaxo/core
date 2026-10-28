<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<section class="rex-main-frame">
    <?php if (isset($this->content) && '' != $this->content && isset($this->sidebar) && '' != $this->sidebar): ?>
    <div class="rex-grid">
        <div id="rex-js-main-content" data-span="lg:8">
            <?= $this->content ?>
        </div>
        <div id="rex-js-main-sidebar" data-span="lg:4">
            <?= $this->sidebar ?>
        </div>
    </div>
    <?php elseif (isset($this->content) && '' != $this->content): ?>
    <div id="rex-js-main-content">
        <?= $this->content ?>
    </div>
    <?php endif ?>
</section>
