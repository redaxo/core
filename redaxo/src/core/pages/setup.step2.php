<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

assert(isset($context) && $context instanceof Context);
assert(isset($successArray) && is_array($successArray));
assert(isset($errorArray) && is_array($errorArray));
assert(isset($cancelSetupBtn));

$content = '';

if (count($successArray) > 0) {
    $content .= '<ul><li>' . implode('</li><li>', $successArray) . '</li></ul>';
}

$buttons = '';
$class = '';
if (count($errorArray) > 0) {
    $class = 'error';
    $content .= implode('', $errorArray);

    $buttons = '<a class="btn btn-setup" href="' . $context->getUrl(['step' => 3]) . '">' . I18n::msg('setup_212') . '</a>';
} else {
    $class = 'success';
    $buttons = '<a class="btn btn-setup" href="' . $context->getUrl(['step' => 3]) . '">' . I18n::msg('setup_210') . '</a>';
}

$security = '';
foreach (Setup::checkPhpSecurity() as $warning) {
    $security .= Message::warning($warning);
}

echo View::title(I18n::msg('setup_200') . $cancelSetupBtn);

$fragment = new Fragment();
$fragment->setVar('class', $class, false);
$fragment->setVar('title', I18n::msg('setup_207'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
echo '<div class="rex-js-setup-section">' . $fragment->parse('core/page/section.php') . '</div>';
echo $security;
