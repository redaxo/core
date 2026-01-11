<?php

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Markdown;
use Redaxo\Core\View\Fragment;

$package = Addon::require(Request::request('package', 'string'));

$license = null;
if (is_readable($package->getPath('LICENSE.md'))) {
    $license = Markdown::factory()->parse(File::require($package->getPath('LICENSE.md')));
} elseif (is_readable($package->getPath('LICENSE'))) {
    $license = nl2br(file_get_contents($package->getPath('LICENSE')));
}

if ($license) {
    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('credits_license'));
    $fragment->setVar('body', $license, false);
    echo '<div id="license"></div>'; // scroll anchor
    echo $fragment->parse('core/page/section.php');
}

echo '<a class="btn btn-back" href="javascript:history.back();">' . I18n::msg('package_back') . '</a>';
echo '<a class="btn" rel="noopener noreferrer" target="_blank" href="https://choosealicense.com/licenses/">' . I18n::msg('credits_explain_license') . ' <i class="fa fa-external-link"></i></a>';
