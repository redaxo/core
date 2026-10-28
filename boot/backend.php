<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Appearance;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\ContentPageBeforeSlices;
use Redaxo\Core\Content\ExtensionPoint\ContentPageHeader;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Exception\HttpException;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Migration\WebRunner;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\ContentSecurityPolicy;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Component\Dropdown;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// Defense in depth on top of the csrf tokens: browsers tell via Fetch Metadata whether a request comes from another
// site. Requests without the header (older browsers, non-browser clients) are left to the tokens.
if (
    !in_array(Request::requestMethod(), ['get', 'head', 'options'], true)
    && !in_array(Core::getRequest()->headers->get('Sec-Fetch-Site', 'same-origin'), ['same-origin', 'none'], true)
) {
    throw new HttpException('Cross-site requests are not allowed.', Response::HTTP_FORBIDDEN);
}

// Deliberately before anything that expects a usable database or a booted addon: this is the entry point for
// instances that are deployed without shell access, and its whole job is to get the schema in shape.
if (WebRunner::isRequested()) {
    WebRunner::handle();
}

// Nothing to log into yet, so say what is missing instead of failing somewhere down in the login.
if (WebRunner::isInstallationPending()) {
    WebRunner::showGettingStarted();
}

// assets which are passed with a cachebuster will be cached very long,
// as we assume their url will change when the underlying content changes
if (Request::get('asset') && Request::get('buster')) {
    /** @psalm-taint-escape file */ // it is not escaped here, but it is validated below via the realpath
    $assetFile = Request::get('asset', 'string');

    // relative to the assets-root
    if (str_starts_with($assetFile, '/assets/')) {
        $assetFile = '..' . $assetFile;
    }

    $fullPath = realpath($assetFile);
    $assetDir = Path::assets();

    if (!$fullPath) {
        throw new NotFoundHttpException('File "' . $assetFile . '" not found.');
    }
    if (!str_starts_with($fullPath, $assetDir)) {
        throw new NotFoundHttpException('Assets can only be streamed from within the assets folder. "' . $fullPath . '" is not within "' . $assetDir . '".');
    }

    $ext = File::extension($assetFile);
    if (!in_array($ext, ['js', 'css'], true)) {
        throw new NotFoundHttpException('Only JS and CSS files can be streamed from the assets folder.');
    }

    $content = File::get($assetFile);
    if (null === $content) {
        throw new NotFoundHttpException('File "' . $assetFile . '" not found.');
    }

    if ('js' === $ext) {
        $js = preg_replace('@^//# sourceMappingURL=.*$@m', '', $content);

        Response::sendCacheControl('max-age=31536000, immutable');
        Response::sendContent($js, 'text/javascript');
    } else {
        // If we are in a directory off the root, add a relative path here back to the root, like "../"
        // get the public path to this file, plus the baseurl
        $relativeroot = '';
        $pubroot = dirname($_SERVER['PHP_SELF']) . '/' . $relativeroot;

        $prefix = $pubroot . dirname($assetFile) . '/';
        $styles = preg_replace('/(url\(["\']?)([^\/"\'])([^\:\)]+["\']?\))/i', '$1' . $prefix . '$2$3', $content);

        Response::sendCacheControl('max-age=31536000, immutable');
        Response::sendContent($styles, 'text/css');
    }
    exit;
}

// ----- verfuegbare seiten
$pages = [];

// ----------------- CREATE LANG OBJ
I18n::setLocale(I18n::$defaultLocale);

// ---- prepare login
$login = new BackendLogin();
BackendLogin::setCurrent($login);

$passkey = Request::post('rex_user_passkey', 'string', null);
$rexUserLogin = Request::post('rex_user_login', 'string');
$rexUserPsw = Request::post('rex_user_psw', 'string');
$rexUserStayLoggedIn = Request::post('rex_user_stay_logged_in', 'boolean', false);

if (Request::get('rex_logout', 'boolean') && CsrfToken::factory('backend_logout')->isValid()) {
    $login->setLogout(true);
    $login->checkLogin();
    CsrfToken::removeAll();

    // "cache" is deliberately omitted, see the comment on the login page below.
    Response::setHeader('Clear-Site-Data', '"storage", "executionContexts"');

    // Not all browsers support the header Clear-Site-Data.
    // we dont kill/regenerate the session so e.g. the frontend will not get logged out
    Session::start()->clear();

    // is necessary for login after logout
    // and without the redirect, the csrf token would be invalid
    Response::sendRedirect(Url::backendController(['rex_logged_out' => 1]));
}

if (($rexUserLogin || $passkey) && !CsrfToken::factory('backend_login')->isValid()) {
    $loginCheck = false;
    $login->message = I18n::msg('csrf_token_invalid');
} else {
    $login->setLogin($rexUserLogin, $rexUserPsw);
    $login->setPasskey('' === $passkey ? null : $passkey);
    $login->setStayLoggedIn($rexUserStayLoggedIn);
    $loginCheck = $login->checkLogin();
}

if (!$loginCheck) {
    if (Request::isXmlHttpRequest()) {
        Response::setStatus(Response::HTTP_UNAUTHORIZED);
    }

    $pages['login'] = Controller::getLoginPage();
    Controller::setCurrentPage('login');

    if ('login' !== Request::request('page', 'string', 'login')) {
        // clear in-browser data of a previous session with the same browser for security reasons.
        // a possible attacker should not be able to access cached data of a previous valid session on the same computer.
        // clearing "executionContext" or "cookies" would result in a endless loop.
        //
        // "cache" is deliberately omitted: browsers hold back the response until the entire HTTP cache
        // has been walked to filter it by origin, which takes seconds on a well-filled profile and
        // therefore delays every backend request made with an expired session.
        // It also buys very little, because backend pages are sent with "no-cache, max-age=0, private"
        // anyway, while the clearing would additionally wipe the cache of the website on the same origin.
        Response::setHeader('Clear-Site-Data', '"storage"');

        // Not all browsers support the header Clear-Site-Data.
        // we dont kill/regenerate the session so e.g. the frontend will not get logged out
        Session::start()->clear();
    }
} else {
    // Userspezifische Sprache einstellen
    $user = Type::notNull($login->getUser());
    $lang = $user->language;
    if ($lang && 'default' != $lang && $lang != I18n::getLocale()) {
        I18n::setLocale($lang);
    }

    Core::setUser($user);
}

if ('' === $login->message && Request::get('rex_logged_out', 'boolean')) {
    $login->message = I18n::msg('login_logged_out');
}

Controller::setPages($pages);

// ----- Prepare Core Pages
if (Core::getUser()) {
    Controller::setCurrentPage(trim(Request::request('page', 'string')));
    Controller::appendLoggedInPages();

    if ('profile' !== Controller::getCurrentPage() && BackendLogin::requireCurrent()->requiresPasswordChange()) {
        Response::sendRedirect(Url::backendPage('profile'));
    }

    if (Request::isPostMaxSizeExceeded()) {
        Extension::register('PAGE_TITLE_SHOWN', static function (ExtensionPoint $ep) {
            return Message::error(I18n::msg('post_max_size_exceeded', Formatter::bytes(Type::string(ini_get('post_max_size'))))) . Type::string($ep->subject);
        });
    }
}

Asset::setJsProperty('backend', true);

$i18n = [
    'overlay_close' => I18n::msg('overlay_close'),
    'password_show' => I18n::msg('password_show'),
    'picker_picked' => I18n::msg('picker_picked'),
    'collection_added' => I18n::msg('collection_added'),
    'collection_removed' => I18n::msg('collection_removed'),
    'clipboard_copied' => I18n::msg('clipboard_copied'),
    'passkey_failed' => I18n::msg('passkey_failed'),
    'dialog_confirm' => I18n::msg('dialog_confirm'),
    'dialog_cancel' => I18n::msg('dialog_cancel'),
    'select_all' => I18n::msg('select_all'),
    'select_none' => I18n::msg('select_none'),
    'select_no_results' => I18n::msg('select_no_results'),
    'select_search' => I18n::msg('select_search'),
];

if (Core::getUser()) {
    $login = BackendLogin::requireCurrent();
    Asset::setJsProperty('session_keep_alive_url', Url::backendController(['page' => 'credits', 'rex-api-call' => 'user_session_status']));
    Asset::setJsProperty('session_logout_url', Url::backendController(['rex_logout' => 1] + CsrfToken::factory('backend_logout')->getUrlParams()));
    Asset::setJsProperty('session_login_url', Url::backendController());
    $sessionPolicy = BackendLogin::getSessionPolicy();
    Asset::setJsProperty('session_keep_alive', $sessionPolicy->keepAlive);
    Asset::setJsProperty('session_duration', $sessionPolicy->duration);
    Asset::setJsProperty('session_max_overall_duration', $sessionPolicy->maxOverallDuration);
    Asset::setJsProperty('session_start', $login->getSessionVar(Login::SESSION_START_TIME));
    Asset::setJsProperty('session_stay_logged_in', $login->getSessionVar(BackendLogin::SESSION_STAY_LOGGED_IN, false));
    Asset::setJsProperty('session_warning_time', $sessionPolicy->warningTime);
    Asset::setJsProperty('session_server_time', time());

    $i18n += [
        'session_timeout_title' => I18n::msg('session_timeout_title'),
        'session_timeout_message_expand' => I18n::msg('session_timeout_message_expand'),
        'session_timeout_message_expired' => I18n::msg('session_timeout_message_expired'),
        'session_timeout_message_has_expired' => I18n::msg('session_timeout_message_has_expired'),
        'session_timeout_logout_label' => I18n::msg('session_timeout_logout_label'),
        'session_timeout_login_label' => I18n::msg('session_timeout_login_label'),
        'session_timeout_refresh_label' => I18n::msg('session_timeout_refresh_label'),
    ];
}

Asset::setJsProperty('i18n', $i18n);

Asset::addCssFile(Url::coreAssets('fonts/font-awesome/css/all.min.css'));
Asset::addCssFile(Url::coreAssets('css/backend.css'));
// styles of the Bootstrap 3 markup, which addons may still output
Asset::addCssFile(Url::coreAssets('css/bootstrap-compat.css'));
Asset::addJsFile(Url::coreAssets('js/backend.js'), [Asset::JS_IMMUTABLE => true, Asset::JS_MODULE => true]);

if (Core::getConfig('article_work_version', false)) {
    Extension::register(ContentPageHeader::class, static function (ContentPageHeader $ep): void {
        if ('content/edit' !== $ep->page) {
            return;
        }

        $articleId = $ep->article->id;

        $version = ArticleRevision::getSessionArticleRevision($articleId);
        $newVersion = Request::request('rex_set_version', 'int', null);

        if (ArticleRevision::LIVE === $newVersion) {
            $version = ArticleRevision::LIVE;
        } elseif (ArticleRevision::WORK === $newVersion) {
            $version = ArticleRevision::WORK;
        }

        if (!Core::requireUser()->hasPerm('version[live_version]')) {
            $version = ArticleRevision::WORK;
        }

        ArticleRevision::setSessionArticleRevision($articleId, $version);

        $ep->sliceRevision = $version;
    });

    Extension::register(ContentPageBeforeSlices::class, static function (ContentPageBeforeSlices $ep): ?string {
        if ('content/edit' !== $ep->page) {
            return null;
        }

        $user = Core::requireUser();
        $articleId = $ep->article->id;
        $languageId = $ep->article->languageId;
        $return = $ep->subject;

        $workingVersionEmpty = true;
        $gw = Sql::factory();
        $gw->setQuery(
            'select * from rex_article_slice where article_id=? and language_id=? and revision=1 LIMIT 1',
            [$articleId, $languageId],
        );
        if ($gw->getRows() > 0) {
            $workingVersionEmpty = false;
        }

        $csrfToken = CsrfToken::factory('structure_version');

        $func = Request::request('rex_version_func', 'string');
        if ('' !== $func && !$csrfToken->isValid()) {
            $return .= Message::error(I18n::msg('csrf_token_invalid'));
            $func = '';
        }

        switch ($func) {
            case 'copy_work_to_live':
                if ($workingVersionEmpty) {
                    $return .= Message::error(I18n::msg('version_warning_working_version_to_live'));
                } elseif ($user->hasPerm('version[live_version]')) {
                    if (true === Core::getConfig('article_history', false)) {
                        ArticleSliceHistory::makeSnapshot($articleId, $languageId, 'work_to_live');
                    }

                    ArticleRevision::copyContent(
                        $articleId,
                        $languageId,
                        ArticleRevision::WORK,
                        ArticleRevision::LIVE,
                    );
                    $return .= Message::success(I18n::msg('version_info_working_version_to_live'));

                    $article = Type::instanceOf(Article::get($articleId, $languageId), Article::class);
                    ArticleRevision::setSessionArticleRevision($articleId, ArticleRevision::LIVE);
                    $ep->sliceRevision = ArticleRevision::LIVE;
                    $return = Extension::dispatch(
                        new ArticleContentUpdated($article, 'work_to_live', subject: $return),
                    );
                }
                break;
            case 'copy_live_to_work':
                ArticleRevision::copyContent(
                    $articleId,
                    $languageId,
                    ArticleRevision::LIVE,
                    ArticleRevision::WORK,
                );
                $return .= Message::success(I18n::msg('version_info_live_version_to_working'));
                ArticleRevision::setSessionArticleRevision($articleId, ArticleRevision::WORK);
                $ep->sliceRevision = ArticleRevision::WORK;
                break;
            case 'clear_work':
                ArticleRevision::clearContent($articleId, $languageId, ArticleRevision::WORK);
                $return .= Message::success(I18n::msg('version_info_clear_workingversion'));
                break;
        }

        $revision = ArticleRevision::getSessionArticleRevision($articleId);

        $revisions = [];
        if ($user->hasPerm('version[live_version]')) {
            $revisions[ArticleRevision::LIVE] = I18n::msg('version_liveversion');
        }
        $revisions[ArticleRevision::WORK] = I18n::msg('version_workingversion');

        $context = new Context([
            'page' => 'content/edit',
            'article_id' => $articleId,
            'language' => $languageId,
            'content_section_id' => $ep->contentSectionId,
        ]);

        $items = [];
        $currentRevision = '';
        foreach ($revisions as $version => $label) {
            $item = [];
            $item['title'] = $label;
            $item['href'] = $context->getUrl(['rex_set_version' => $version]);
            if ($revision == $version) {
                $item['active'] = true;
                $currentRevision = $label;
            }
            $items[] = $item;
        }

        $toolbar = '';

        $toolbar .= '<li class="dropdown">' . (string) new Dropdown(
            $items,
            prefix: Html::raw('<b>' . escape($currentRevision) . '</b>'),
            disabled: !$user->hasPerm('version[live_version]'),
            link: true,
            wrapped: false,
        )->render() . '</li>';

        if (!$user->hasPerm('version[live_version]')) {
            if ($revision > 0) {
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work'] + $csrfToken->getUrlParams()) . '">' . I18n::msg('version_copy_from_liveversion') . '</a></li>';
                $toolbar .= '<li><a href="' . Url::article($articleId, $languageId, ['rex_version' => ArticleRevision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . I18n::msg('version_preview') . '</a></li>';
            }
        } else {
            if ($revision > 0) {
                if (!$workingVersionEmpty) {
                    $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'clear_work'] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('version_confirm_clear_workingversion') . '">' . I18n::msg('version_clear_workingversion') . '</a></li>';
                    $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_work_to_live'] + $csrfToken->getUrlParams()) . '">' . I18n::msg('version_working_to_live') . '</a></li>';
                }
                $toolbar .= '<li><a href="' . Url::article($articleId, $languageId, ['rex_version' => ArticleRevision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . I18n::msg('version_preview') . '</a></li>';
            } else {
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work'] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('version_confirm_copy_live_to_workingversion') . '">' . I18n::msg('version_copy_live_to_workingversion') . '</a></li>';
            }
        }

        $inverse = ArticleRevision::WORK == $revision;
        $cssClass = ArticleRevision::WORK == $revision ? 'rex-state-inprogress' : 'rex-state-live';

        $return .= View::toolbar('<ul class="rex-toolbar-nav" role="list">' . $toolbar . '</ul>', null, $cssClass, $inverse);

        return $return;
    });
}

Permission::register('users[]');

Permission::register('addArticle[]', null, Permission::OPTIONS);
Permission::register('addCategory[]', null, Permission::OPTIONS);
Permission::register('editArticle[]', null, Permission::OPTIONS);
Permission::register('editCategory[]', null, Permission::OPTIONS);
Permission::register('deleteArticle[]', null, Permission::OPTIONS);
Permission::register('deleteCategory[]', null, Permission::OPTIONS);
Permission::register('moveArticle[]', null, Permission::OPTIONS);
Permission::register('moveCategory[]', null, Permission::OPTIONS);
Permission::register('copyArticle[]', null, Permission::OPTIONS);
Permission::register('copyContent[]', null, Permission::OPTIONS);
Permission::register('publishArticle[]', null, Permission::OPTIONS);
Permission::register('publishCategory[]', null, Permission::OPTIONS);
Permission::register('article2startarticle[]', null, Permission::OPTIONS);
Permission::register('article2category[]', null, Permission::OPTIONS);
Permission::register('moveSlice[]', null, Permission::OPTIONS);
Permission::register('publishSlice[]', null, Permission::OPTIONS);
Permission::register('linkmap[all_categories]', null, Permission::OPTIONS);

if (Core::getConfig('article_history', false)) {
    Permission::register('history[article_rollback]', null, Permission::OPTIONS);
}
if (Core::getConfig('article_work_version', false)) {
    Permission::register('version[live_version]', null, Permission::OPTIONS);
}

// ----- INCLUDE ADDONS
include_once Path::core('boot/addons.php');

Asset::setJsProperty('theme', Appearance::getTheme() ?? 'auto');

// ----- Prepare AddOn Pages
if (Core::getUser()) {
    Controller::appendPackagePages();
}

$pages = Extension::dispatch(new ExtensionPoint('PAGES_PREPARED', Controller::getPages()));
Controller::setPages($pages);

// Set Startpage
if ($user = Core::getUser()) {
    if (BackendLogin::requireCurrent()->requiresPasswordChange()) {
        // profile is available for everyone, no additional checks required
        Controller::setCurrentPage('profile');
    } elseif (!Controller::getCurrentPage()) {
        // trigger api functions before page permission check/redirection, if page param is not set.
        // the api function is responsible for checking permissions.
        ApiFunction::handleCall();
    }

    // --- page pruefen und benoetigte rechte checken
    Controller::checkPagePermissions($user);
}
$page = Controller::getCurrentPage();
Asset::setJsProperty('page', $page);

if ('content' == Controller::getCurrentPagePart(1)) {
    Controller::getPageObject('structure')->setIsActive(true);
}

// ----- EXTENSION POINT
// page variable validated
Extension::dispatch(new ExtensionPoint('PAGE_CHECKED', $page, ['pages' => $pages], true));

if ($page) {
    // trigger api functions after PAGE_CHECKED, if page param is set
    // the api function is responsible for checking permissions.
    ApiFunction::handleCall();
}

// include the requested backend page
Controller::includeCurrentPage();

// ----- caching end für output filter
$CONTENT = ob_get_clean();

// ----- inhalt ausgeben
Response::setHeader('Content-Security-Policy', ContentSecurityPolicy::backend()->toString());
Response::sendPage($CONTENT);
