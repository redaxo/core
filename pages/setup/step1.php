<?php

use Redaxo\Core\Http\Context;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

assert(isset($context) && $context instanceof Context);
assert(isset($cancelSetupBtn));

Setup::init();

$initial = Setup::isInitialSetup();
$current = I18n::getLocale();

$langs = [];
foreach (I18n::getLocales() as $locale) {
    $label = I18n::msgInLocale('lang', $locale);
    $active = !$initial && $current === $locale ? ' active' : '';
    $langs[$label] = '<a class="list-group-item' . $active . '" href="' . $context->getUrl(['lang' => $locale, 'step' => 2]) . '">' . $label . '</a>';
}
ksort($langs);
echo View::title(I18n::msg('setup_100') . $cancelSetupBtn);
$content = '<div class="list-group">' . implode('', $langs) . '</div>';

$fragment = new Fragment();
$fragment->setVar('heading', I18n::msg('setup_101'), false);
$fragment->setVar('content', $content, false);

if (!$initial) {
    $buttons = '<a class="btn btn-setup" href="' . $context->getUrl(['lang' => $current, 'step' => 2]) . '">' . I18n::msg('setup_110') . '</a>';

    $fragment->setVar('buttons', $buttons, false);
}

echo $fragment->parse('core/page/section.php');
