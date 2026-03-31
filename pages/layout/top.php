<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\ApiFunction\UserImpersonate;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/** Layout Kopf des Backends. */

$curPage = Controller::requireCurrentPageObject();
$user = Core::getUser();

if (Request::isPJAXRequest()) {
    // add title to the page, so pjax can update it. see gh#136
    echo '<title>' . escape(Controller::getPageTitle()) . '</title>';
}

if (!$curPage->hasLayout()) {
    if (Request::isPJAXRequest()) {
        echo '<section class="rex-page-main-inner" id="rex-js-page-main" data-pjax-container="#rex-js-page-main">';
    }

    return;
}

$bodyAttr = [];

// Str::normalize requires intl extension, which may not exist before extensions check in setup
$bodyId = Core::isSetup() ? 'setup' : Str::normalize(Controller::getCurrentPage(), '-', ' ');

$bodyAttr['id'] = ['rex-page-' . $bodyId];

$bodyAttr['class'] = ['rex-is-logged-out'];
if ($user) {
    $bodyAttr['class'] = ['rex-is-logged-in'];
}
if (Core::isDebugMode()) {
    $bodyAttr['class'][] = 'rex-is-debugmode';
}
if (Core::isSafeMode()) {
    $bodyAttr['class'][] = 'rex-is-safemode';
}
if ($curPage->isPopup()) {
    $bodyAttr['class'][] = 'rex-is-popup';
}
if (Core::getImpersonator()) {
    $bodyAttr['class'][] = 'rex-is-impersonated';
}

$bodyAttr['class'][] = 'rex-has-theme';
if (Core::getProperty('theme')) {
    // global theme from config.yml
    $bodyAttr['class'][] = 'rex-theme-' . escape((string) Core::getProperty('theme'));
} elseif ($user && $user->getValue('theme')) {
    // user selected theme
    $bodyAttr['class'][] = 'rex-theme-' . escape($user->getValue('theme'));
}

// ----- EXTENSION POINT
$bodyAttr = Extension::registerPoint(new ExtensionPoint('PAGE_BODY_ATTR', $bodyAttr));

$body = '';
foreach ($bodyAttr as $k => $v) {
    $body .= ' ' . $k . '="';
    if (is_array($v)) {
        $body .= implode(' ', $v);
    }
    $body .= '"';
}

$hasNavigation = $curPage->hasNavigation();

$metaItems = [];
if ($user && $hasNavigation) {
    if (Core::isSafeMode() && $user->isAdmin()) {
        $item = [];
        $item['title'] = I18n::msg('safemode_deactivate');
        $item['href'] = Url::backendController(['safemode' => 0]);
        $item['attributes'] = 'class="btn btn-safemode-deactivate" data-pjax="false"';
        $metaItems[] = $item;
        unset($item);
    }

    $userName = $user->getName() ?: $user->getLogin();
    $impersonator = Core::getImpersonator();
    if ($impersonator) {
        $impersonator = $impersonator->getName() ?: $impersonator->getLogin();
    }

    $item = [];
    $item['title'] = '<span class="text-muted">' . I18n::msg('logged_in_as') . '</span> <a class="rex-username" href="' . Url::backendPage('profile') . '" title="' . I18n::msg('profile_title') . '"><i class="rex-icon rex-icon-user"></i> ' . escape($userName) . '</a>';
    if ($impersonator) {
        $item['title'] .= ' (<i class="rex-icon rex-icon-user"></i> ' . escape($impersonator) . ')';
    }
    $metaItems[] = $item;
    unset($item);

    $item = [];
    $item['attributes'] = 'class="rex-logout"';
    if ($impersonator) {
        $item['title'] = '<i class="rex-icon rex-icon-sign-out"></i> ' . I18n::msg('login_depersonate');
        $item['href'] = Url::currentBackendPage(['_impersonate' => '_depersonate'] + UserImpersonate::getUrlParams());
        $item['attributes'] .= ' data-pjax="false"';
    } else {
        $item['title'] = '<i class="rex-icon rex-icon-sign-out"></i> ' . I18n::msg('logout');
        $item['href'] = Url::backendController(['rex_logout' => 1] + CsrfToken::factory('backend_logout')->getUrlParams());
    }
    $metaItems[] = $item;
    unset($item);
} elseif ($hasNavigation && !Core::isSetup()) {
    $item = [];
    $item['title'] = I18n::msg('logged_out');
    $metaItems[] = $item;
    unset($item);
}

// wird in bottom.php an Fragment uebergeben
$navigation = '';
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

    $n = Extension::registerPoint(new ExtensionPoint('PAGE_NAVIGATION', $n));

    $blocks = $n->getNavigation();

    $navigation = '';
    foreach ($blocks as $block) {
        $fragment = new Fragment();
        $fragment->setVar('headline', $block['headline'], false);
        $fragment->setVar('items', $block['navigation'], false);
        $navigation .= $fragment->parse('core/navigations/main.php');
    }
}

/* Setup Navigation ********************************************************** */
if ('setup' == Controller::getCurrentPagePart(1)) {
    $step = Request::request('step', 'float');
    $lang = Request::request('lang', 'string', '');

    $context = Setup::getContext();

    $navi = [];
    $end = $lang ? 6 : 1;
    for ($i = 1; $i <= $end; ++$i) {
        $n = [];
        if (!$step || $i == $step) {
            $n['active'] = true;
        }

        $n['href'] = 'javascript:void(0)';
        if ($i < $step) {
            $n['itemAttr']['class'][] = 'bg-success';
            $n['href'] = $context->getUrl(['step' => $i]);
            if (7 == $step) {
                $n['href'] = 'javascript:void(0)';
            }
        }

        if ($step && $i > $step) {
            $n['itemAttr']['class'][] = 'disabled';
        }

        if (isset($n['href']) && '' != $lang) {
            $name = I18n::msg('setup_' . $i . '99');
        } else {
            $name = '<span>' . I18n::msg('setup_' . $i . '99') . '</span>';
        }

        $n['title'] = $name;

        $navi[] = $n;
    }

    $fragment = new Fragment();
    $fragment->setVar('headline', ['title' => 'Setup'], false);
    $fragment->setVar('items', $navi, false);
    $navigation = $fragment->parse('core/navigations/main.php');
}

/* PJAX Footer Header ********************************************************** */
if (!Request::isPJAXContainer('#rex-js-page-container')) {
    $fragment = new Fragment();
    $fragment->setVar('pageTitle', Controller::getPageTitle());
    $fragment->setVar('cssFiles', Asset::getCssFiles());
    $fragment->setVar('jsFiles', Asset::getJsFilesWithOptions());
    $fragment->setVar('jsProperties', json_encode(Asset::getJsProperties()), false);
    $fragment->setVar('favicon', Asset::getFavicon());
    $fragment->setVar('pageHeader', Extension::registerPoint(new ExtensionPoint('PAGE_HEADER', '')), false);
    $fragment->setVar('bodyAttr', $body, false);
    echo $fragment->parse('core/top.php');

    $fragment = new Fragment();
    $fragment->setVar('items', $metaItems, false);
    $metaNavigation = $fragment->parse('core/navigations/meta.php');

    $fragment = new Fragment();
    // $fragment->setVar('pageHeader', Extension::registerPoint(new ExtensionPoint('PAGE_HEADER', '')), false);
    $fragment->setVar('meta_navigation', $metaNavigation, false);
    echo $fragment->parse('core/header.php');
}

echo '<div id="rex-js-page-container" class="rex-page-container">';

$fragment = new Fragment();
$fragment->setVar('navigation', $navigation, false);
echo $fragment->parse('core/navigation.php');

$pjax = $curPage->allowsPjax() ? ' data-pjax-container="#rex-js-page-main"' : '';

?><div class="rex-page-main"><section class="rex-page-main-inner" id="rex-js-page-main"<?= $pjax ?>>
