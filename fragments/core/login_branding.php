<?php
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<div class="rex-branding">
    <?= File::get(Path::coreAssets('redaxo-logo.svg')) ?>
</div>
