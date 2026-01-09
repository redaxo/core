<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

?>
<div class="btn-group<?= (isset($this->vertical) && $this->vertical) ? '-vertical' : '' ?><?= (isset($this->size) && '' != trim($this->size)) ? ' btn-group-' . $this->size : '' ?>">
    <?php $this->subfragment('core/buttons/button.php') ?>
</div>
