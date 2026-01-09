<?php
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$target = 'rex-js-collapse-' . random_int(100, 999) . random_int(100, 999);

?>

<nav class="navbar navbar-<?= (isset($this->inverse) && $this->inverse) ? 'inverse' : 'default' ?><?= (empty($this->brand)) ? ' rex-navbar-without-brand' : '' ?><?= (isset($this->cssClass) && '' != $this->cssClass) ? ' ' . $this->cssClass : '' ?>">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#<?= $target ?>">
                <span class="sr-only">Toggle</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <?php if (isset($this->brand) && '' != $this->brand): ?>
                <span class="navbar-brand">
                    <?= $this->brand ?>
                </span>
            <?php endif ?>
        </div>
        <div class="collapse navbar-collapse" id="<?= $target ?>">
            <?php if (isset($this->content) && '' != $this->content): ?>
                <?= $this->content ?>
            <?php endif ?>
        </div>
    </div>
</nav>
