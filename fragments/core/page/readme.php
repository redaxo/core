<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<div class="rex-readme">
    <article class="rex-readme-content"><?= $this->getVar('content') ?></article>
</div>
