<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<section class="rex-main-frame">
    <?php if (isset($this->content) && '' != $this->content && isset($this->sidebar) && '' != $this->sidebar): ?>
    <div class="rex-grid" data-from="lg" data-columns="8 4">
        <div id="rex-js-main-content">
            <?= $this->content ?>
        </div>
        <div id="rex-js-main-sidebar">
            <?= $this->sidebar ?>
        </div>
    </div>
    <?php elseif (isset($this->content) && '' != $this->content): ?>
    <div id="rex-js-main-content">
        <?= $this->content ?>
    </div>
    <?php endif ?>
</section>
