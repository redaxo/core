<?php

$content = '';

$package = rex_package::get(rex_request('package', 'string'));
$name = $package->getPackageId();
$version = $package->getVersion();
$author = $package->getAuthor();
$supportPage = $package->getSupportPage();
if (is_readable($package->getPath('help.php'))) {
    if (!$package->isAvailable() && is_readable($package->getPath('lang'))) {
        rex_i18n::addDirectory($package->getPath('lang'));
    }
    ob_start();
    $package->includeFile('help.php');
    $content .= ob_get_clean();
} elseif (is_readable($package->getPath('README.' . rex_i18n::getLanguage() . '.md'))) {
    [$readmeToc, $readmeContent] = rex_markdown::factory()->parseWithToc(rex_file::require($package->getPath('README.' . rex_i18n::getLanguage() . '.md')), 2, 3, [
        rex_markdown::SOFT_LINE_BREAKS => false,
        rex_markdown::HIGHLIGHT_PHP => true,
    ]);
    $fragment = new rex_fragment();
    $fragment->setVar('content', $readmeContent, false);
    $fragment->setVar('toc', $readmeToc, false);
    $content .= $fragment->parse('core/page/docs.php');
} elseif (is_readable($package->getPath('README.md'))) {
    [$readmeToc, $readmeContent] = rex_markdown::factory()->parseWithToc(rex_file::require($package->getPath('README.md')), 2, 3, [
        rex_markdown::SOFT_LINE_BREAKS => false,
        rex_markdown::HIGHLIGHT_PHP => true,
    ]);
    $fragment = new rex_fragment();
    $fragment->setVar('content', $readmeContent, false);
    $fragment->setVar('toc', $readmeToc, false);
    $content .= $fragment->parse('core/page/docs.php');
} else {
    $content .= rex_view::info(rex_i18n::msg('package_no_help_file'));
}

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('package_hhelp'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$credits = '';
$credits .= '<dl class="dl-horizontal">';
$credits .= '<dt>' . rex_i18n::msg('credits_name') . '</dt><dd>' . rex_escape($name) . '</dd>';

if ($version) {
    $credits .= '<dt>' . rex_i18n::msg('credits_version') . '</dt><dd>' . rex_escape($version) . '</dd>';
}
if ($author) {
    $credits .= '<dt>' . rex_i18n::msg('credits_author') . '</dt><dd>' . rex_escape($author) . '</dd>';
}
if ($supportPage) {
    $credits .= '<dt>' . rex_i18n::msg('credits_supportpage') . '</dt><dd><a href="' . rex_escape($supportPage) . '" onclick="window.open(this.href); return false;">' . rex_escape($supportPage) . ' <i class="fa fa-external-link"></i></a></dd>';
}

$credits .= '</dl>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('credits'), false);
$fragment->setVar('body', $credits, false);
echo $fragment->parse('core/page/section.php');

echo '<p><a class="btn btn-back" href="' . rex_url::backendPage('packages') . '">' . rex_i18n::msg('package_back') . '</a></p>';
