<?php

use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\View\Component\LoginBranding;

return static function (LoginBranding $loginBranding): void { ?>
<div class="rex-branding">
    <?= File::get(Path::coreAssets('redaxo-logo.svg')) ?>
</div>
<?php };
