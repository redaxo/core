<?php

use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Layout\MainNavigation;
use Redaxo\Core\Backend\Layout\TopBar;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Security\ApiFunction\UserImpersonate;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

use function Redaxo\Core\View\escape;

/** Layout Kopf des Backends. */

$curPage = Controller::requireCurrentPageObject();
$user = Core::getUser();

if (!$curPage->hasLayout()) {
    return;
}

$bodyAttr = [];

$bodyAttr['id'] = ['rex-page-' . Str::normalize(Controller::getCurrentPage(), '-', ' ')];

$bodyAttr['class'] = ['rex-is-logged-out'];
if ($user) {
    $bodyAttr['class'] = ['rex-is-logged-in'];
}
if (Core::isDevMode()) {
    $bodyAttr['class'][] = 'rex-is-devmode';
}
if ($curPage->isPopup()) {
    $bodyAttr['class'][] = 'rex-is-popup';
}
if (Core::getImpersonator()) {
    $bodyAttr['class'][] = 'rex-is-impersonated';
}

if ($theme = Appearance::getTheme()) {
    $bodyAttr['class'][] = 'rex-theme-' . $theme;
}

// ----- EXTENSION POINT
$bodyAttr = Extension::dispatch(new ExtensionPoint('PAGE_BODY_ATTR', $bodyAttr));

$body = '';
foreach ($bodyAttr as $k => $v) {
    $body .= ' ' . $k . '="';
    if (is_array($v)) {
        $body .= implode(' ', $v);
    }
    $body .= '"';
}

$hasNavigation = $curPage->hasNavigation();

// list items of the meta navigation in the top bar
$metaItems = [];
if ($user && $hasNavigation) {
    $userName = $user->name ?: $user->login;
    $impersonator = Core::getImpersonator();
    if ($impersonator) {
        $impersonator = $impersonator->name ?: $impersonator->login;
    }

    $item = '<li><span class="rex-topbar-muted">' . I18n::msg('logged_in_as') . '</span> <a href="' . Url::backendPage('profile') . '" title="' . I18n::msg('profile_title') . '"><i class="rex-icon rex-icon-user" aria-hidden="true"></i> ' . escape($userName) . '</a>';
    if ($impersonator) {
        $item .= ' (<i class="rex-icon rex-icon-user"></i> ' . escape($impersonator) . ')';
    }
    $metaItems[] = $item . '</li>';

    if ($impersonator) {
        $metaItems[] = '<li><a href="' . escape(Url::currentBackendPage(['_impersonate' => '_depersonate'] + UserImpersonate::getUrlParams())) . '"><i class="rex-icon rex-icon-sign-out" aria-hidden="true"></i> ' . I18n::msg('login_depersonate') . '</a></li>';
    } else {
        $metaItems[] = '<li><a href="' . escape(Url::backendController(['rex_logout' => 1] + CsrfToken::factory('backend_logout')->getUrlParams())) . '"><i class="rex-icon rex-icon-sign-out" aria-hidden="true"></i> ' . I18n::msg('logout') . '</a></li>';
    }
} elseif ($hasNavigation) {
    $metaItems[] = '<li>' . I18n::msg('logged_out') . '</li>';
}
$metaItems = Extension::dispatch(new ExtensionPoint('META_NAVI', $metaItems));

$navigation = null;
if ($user && $hasNavigation) {
    $n = Navigation::factory();
    foreach (Controller::getPages() as $p => $pageObj) {
        $p = strtolower($p);
        if ($pageObj instanceof MainPage) {
            $pageObj->setItemAttr('id', 'rex-navi-page-' . strtolower(preg_replace('/[^a-zA-Z0-9\-]*/', '', str_replace('_', '-', $p))));

            if (!$pageObj->getBlock()) {
                $pageObj->setBlock('addons');
            }

            if (!$pageObj->getHref()) {
                $pageObj->setHref(Url::backendPage($p));
            }

            $n->addPage($pageObj);
        }
    }

    $n = Extension::dispatch(new ExtensionPoint('PAGE_NAVIGATION', $n));

    $navigation = new MainNavigation($n->getNavigation());
}

$fragment = new Fragment();
$fragment->setVar('pageTitle', Controller::getPageTitle());
$fragment->setVar('cssFiles', Asset::getCssFiles());
$fragment->setVar('jsFiles', Asset::getJsFilesWithOptions());
$fragment->setVar('jsProperties', json_encode(Asset::getJsProperties()), false);
$fragment->setVar('favicon', Asset::getFavicon());
$fragment->setVar('pageHeader', Extension::dispatch(new ExtensionPoint('PAGE_HEADER', '')), false);
$fragment->setVar('bodyAttr', $body, false);
echo $fragment->parse('core/top.php');

echo new TopBar(
    array_map(Html::raw(...), $metaItems),
    popup: $curPage->isPopup(),
    login: 'login' === Controller::getCurrentPage(),
)->render();

echo '<div id="rex-js-page-container" class="rex-shell">';

echo $navigation?->render();

?><div class="rex-main"><main class="rex-main-inner" id="rex-js-page-main" tabindex="-1">
