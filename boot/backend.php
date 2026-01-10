<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Style;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\HistoryLogin;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\MetaInfo;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Asset;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");

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
        Response::sendContent($js, 'application/javascript');
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

// ----------------- SETUP
if (Core::isSetup()) {
    // ----------------- SET SETUP LANG
    $requestLang = Request::request('lang', 'string', Core::getProperty('lang'));
    if (in_array($requestLang, I18n::getLocales())) {
        Core::setProperty('lang', $requestLang);
    } else {
        Core::setProperty('lang', 'en_gb');
    }

    I18n::setLocale(Core::getProperty('lang'));

    $pages['setup'] = Controller::getSetupPage();
    Controller::setCurrentPage('setup');
} else {
    // ----------------- CREATE LANG OBJ
    I18n::setLocale(Core::getProperty('lang'));

    // ---- prepare login
    $login = new BackendLogin();
    Core::setProperty('login', $login);

    $passkey = Request::post('rex_user_passkey', 'string', null);
    $rexUserLogin = Request::post('rex_user_login', 'string');
    $rexUserPsw = Request::post('rex_user_psw', 'string');
    $rexUserStayLoggedIn = Request::post('rex_user_stay_logged_in', 'boolean', false);

    if (Request::get('rex_logout', 'boolean') && CsrfToken::factory('backend_logout')->isValid()) {
        $login->setLogout(true);
        $login->checkLogin();
        CsrfToken::removeAll();

        $userAgent = Request::server('HTTP_USER_AGENT');
        $advertisedChrome = preg_match('/(Chrome|CriOS)\//i', $userAgent);
        $nonChrome = preg_match('/(Aviator|ChromePlus|coc_|Dragon|Edge|Flock|Iron|Kinza|Maxthon|MxNitro|Nichrome|OPR|Perk|Rockmelt|Seznam|Sleipnir|Spark|UBrowser|Vivaldi|WebExplorer|YaBrowser)/i', $userAgent);
        if ($advertisedChrome && !$nonChrome) {
            // Browser is likely Google Chrome which currently seems to be super slow when clearing 'cache' from site data
            // https://bugs.chromium.org/p/chromium/issues/detail?id=762417
            Response::setHeader('Clear-Site-Data', '"storage", "executionContexts"');
        } else {
            Response::setHeader('Clear-Site-Data', '"cache", "storage", "executionContexts"');
        }

        // Currently browsers like Safari do not support the header Clear-Site-Data.
        // we dont kill/regenerate the session so e.g. the frontend will not get logged out
        Request::clearSession();

        // is necessary for login after logout
        // and without the redirect, the csrf token would be invalid
        Response::sendRedirect(Url::backendController(['rex_logged_out' => 1]));
    }

    global $rexUserLoginmessage;
    $rexUserLoginmessage = '';

    if (($rexUserLogin || $passkey) && !CsrfToken::factory('backend_login')->isValid()) {
        $loginCheck = I18n::msg('csrf_token_invalid');
    } else {
        // the server side encryption of pw is only required
        // when not already encrypted by client using javascript
        $login->setLogin($rexUserLogin, $rexUserPsw, Request::post('javascript', 'boolean'));
        $login->setPasskey('' === $passkey ? null : $passkey);
        $login->setStayLoggedIn($rexUserStayLoggedIn);
        $loginCheck = $login->checkLogin();
    }

    if (true !== $loginCheck) {
        if (Request::isXmlHttpRequest()) {
            Response::setStatus(Response::HTTP_UNAUTHORIZED);
        }

        // login failed
        $rexUserLoginmessage = $login->getMessage();

        // Fehlermeldung von der Datenbank
        if (is_string($loginCheck)) {
            $rexUserLoginmessage = $loginCheck;
        }

        $pages['login'] = Controller::getLoginPage();
        Controller::setCurrentPage('login');

        if ('login' !== Request::request('page', 'string', 'login')) {
            // clear in-browser data of a previous session with the same browser for security reasons.
            // a possible attacker should not be able to access cached data of a previous valid session on the same computer.
            // clearing "executionContext" or "cookies" would result in a endless loop.
            Response::setHeader('Clear-Site-Data', '"cache", "storage"');

            // Currently browsers like Safari do not support the header Clear-Site-Data.
            // we dont kill/regenerate the session so e.g. the frontend will not get logged out
            Request::clearSession();
        }
    } else {
        // Userspezifische Sprache einstellen
        $user = $login->getUser();
        $lang = $user->getLanguage();
        if ($lang && 'default' != $lang && $lang != Core::getProperty('lang')) {
            I18n::setLocale($lang);
        }

        Core::setProperty('user', $user);

        // Safe Mode
        if (!Core::isLiveMode() && $user->isAdmin() && null !== ($safeMode = Request::get('safemode', 'boolean', null))) {
            if ($safeMode) {
                Request::setSession('safemode', true);
            } else {
                Request::unsetSession('safemode');
                if (Core::getProperty('safe_mode')) {
                    $configFile = Path::coreData('config.yml');
                    $config = array_merge(
                        File::getConfig(Path::core('default.config.yml')),
                        File::getConfig($configFile),
                    );
                    $config['safe_mode'] = false;
                    File::putConfig($configFile, $config);
                }
            }
        }
    }

    if ('' === $rexUserLoginmessage && Request::get('rex_logged_out', 'boolean')) {
        $rexUserLoginmessage = I18n::msg('login_logged_out');
    }
}

Controller::setPages($pages);

// ----- Prepare Core Pages
if (Core::getUser()) {
    Controller::setCurrentPage(trim(Request::request('page', 'string')));
    Controller::appendLoggedInPages();

    if ('profile' !== Controller::getCurrentPage() && Core::getProperty('login')->requiresPasswordChange()) {
        Response::sendRedirect(Url::backendPage('profile'));
    }
}

Asset::addJsFile(Url::coreAssets('jquery.min.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('jquery-ui.custom.min.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('jquery-pjax.min.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('standard.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('sha1.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('clipboard-copy-element.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('js/mediapool.js'), [Asset::JS_IMMUTABLE]);

Asset::setJsProperty('backend', true);
Asset::setJsProperty('accesskeys', Core::getProperty('use_accesskeys'));
Asset::setJsProperty('cookie_params', Login::getCookieParams());
Asset::setJsProperty('imageExtensions', Core::getProperty('image_extensions'));

if (Core::getUser()) {
    Asset::addJsFile(Url::coreAssets('session-timeout.js'), [Asset::JS_IMMUTABLE => true]);

    $login = Core::getProperty('login');
    Asset::setJsProperty('session_keep_alive_url', Url::backendController(['page' => 'credits', 'rex-api-call' => 'user_session_status']));
    Asset::setJsProperty('session_logout_url', Url::backendController(['rex_logout' => 1] + CsrfToken::factory('backend_logout')->getUrlParams()));
    Asset::setJsProperty('session_login_url', Url::backendController());
    Asset::setJsProperty('session_keep_alive', Core::getProperty('session_keep_alive', 0));
    Asset::setJsProperty('session_duration', Core::getProperty('session_duration', 0));
    Asset::setJsProperty('session_max_overall_duration', Core::getProperty('session_max_overall_duration', 0));
    Asset::setJsProperty('session_start', $login->getSessionVar(Login::SESSION_START_TIME));
    Asset::setJsProperty('session_stay_logged_in', $login->getSessionVar(BackendLogin::SESSION_STAY_LOGGED_IN, false));
    Asset::setJsProperty('session_warning_time', Core::getProperty('session_warning_time', 300));
    Asset::setJsProperty('session_server_time', time());

    Asset::setJsProperty('i18n', [
        'session_timeout_title' => I18n::msg('session_timeout_title'),
        'session_timeout_message_expand' => I18n::msg('session_timeout_message_expand'),
        'session_timeout_message_expired' => I18n::msg('session_timeout_message_expired'),
        'session_timeout_message_has_expired' => I18n::msg('session_timeout_message_has_expired'),
        'session_timeout_logout_label' => I18n::msg('session_timeout_logout_label'),
        'session_timeout_login_label' => I18n::msg('session_timeout_login_label'),
        'session_timeout_refresh_label' => I18n::msg('session_timeout_refresh_label'),
    ]);
}

Asset::addCssFile(Url::coreAssets('css/styles.css'));
Asset::addCssFile(Url::coreAssets('css/bootstrap-select.min.css'));
Asset::addJsFile(Url::coreAssets('js/bootstrap.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('js/bootstrap-select.min.js'), [Asset::JS_IMMUTABLE => true]);
$bootstrapSelectLang = [
    'de_de' => 'de_DE',
    'en_gb' => 'en_US',
][I18n::getLocale()] ?? 'en_US';
Asset::addJsFile(Url::coreAssets('js/bootstrap-select-defaults-' . $bootstrapSelectLang . '.min.js'), [Asset::JS_IMMUTABLE => true]);
Asset::addJsFile(Url::coreAssets('js/main.js'), [Asset::JS_IMMUTABLE => true]);

Asset::addCssFile(Url::coreAssets('css/redaxo.css'));
Asset::addJsFile(Url::coreAssets('js/redaxo.js'), [Asset::JS_IMMUTABLE => true]);

if (Core::getUser()) {
    /* Customizer Ergänzungen */
    Asset::addCssFile(Url::coreAssets('css/customizer.css'));
    Asset::addJsFile(Url::coreAssets('js/customizer.js'), [Asset::JS_IMMUTABLE => true]);

    if ('' != Core::getConfig('be_style_labelcolor')) {
        Asset::setJsProperty('customizer_labelcolor', Core::getConfig('be_style_labelcolor'));
    }
    if (Core::getConfig('be_style_showlink')) {
        Asset::setJsProperty(
            'customizer_showlink',
            '<h1 class="be-style-customizer-title"><a href="' . Url::frontend() . '" target="_blank" rel="noreferrer noopener"><span class="be-style-customizer-title-name">' . escape(Core::getServerName()) . '</span><i class="rex-icon rex-icon-external-link"></i></a></h1>',
        );
    }

    Asset::addJsFile(Url::coreAssets('js/linkmap.js'), [Asset::JS_IMMUTABLE => true]);

    if ('content' == Controller::getCurrentPagePart(1)) {
        Asset::addJsFile(Url::coreAssets('js/content.js'), [Asset::JS_IMMUTABLE => true]);
    }
}

if (Core::getConfig('article_history', false) && Core::getUser()?->hasPerm('history[article_rollback]')) {
    Asset::addCssFile(Url::coreAssets('noUiSlider/nouislider.css'));
    Asset::addJsFile(Url::coreAssets('noUiSlider/nouislider.js'), [Asset::JS_IMMUTABLE => true]);
    Asset::addCssFile(Url::coreAssets('css/history.css'));
    Asset::addJsFile(Url::coreAssets('js/history.js'), [Asset::JS_IMMUTABLE => true]);

    switch (Request::request('rex_history_function', 'string')) {
        case 'snap':
            $articleId = Request::request('history_article_id', 'int');
            $clangId = Request::request('history_clang_id', 'int');
            $historyDate = Request::request('history_date', 'string');
            ArticleSliceHistory::restoreSnapshot($historyDate, $articleId, $clangId);

            // no break
        case 'layer':
            $articleId = Request::request('history_article_id', 'int');
            $clangId = Request::request('history_clang_id', 'int');
            $versions = ArticleSliceHistory::getSnapshots($articleId, $clangId);

            $select1 = [];
            $select1[] = '<option value="0" selected="selected" data-revision="0">' . I18n::msg('structure_history_current_version') . '</option>';
            if (true === Core::getConfig('article_work_version', false)) {
                $select1[] = '<option value="1" data-revision="1">' . I18n::msg('version_workingversion') . '</option>';
            }

            $select2 = [];
            $select2[] = '<option value="" selected="selected">' . I18n::msg('structure_history_current_version') . '</option>';
            foreach ($versions as $version) {
                $historyInfo = $version['history_date'];
                if ('' != $version['history_user']) {
                    $historyInfo = $version['history_date'] . ' [' . $version['history_user'] . ']';
                }
                $select2[] = '<option value="' . strtotime($version['history_date']) . '" data-history-date="' . escape($version['history_date']) . '">' . escape($historyInfo) . '</option>';
            }

            $content1select = '<select id="content-history-select-date-1" class="content-history-select" data-iframe="content-history-iframe-1" style="">' . implode('', $select1) . '</select>';
            $content1iframe = '<iframe id="content-history-iframe-1" class="history-iframe"></iframe>';
            $content2select = '<select id="content-history-select-date-2" class="content-history-select" data-iframe="content-history-iframe-2">' . implode('', $select2) . '</select>';
            $content2iframe = '<iframe id="content-history-iframe-2" class="history-iframe"></iframe>';

            // fragment holen und ausgeben
            $fragment = new Fragment();
            $fragment->setVar('title', I18n::msg('structure_history_overview_versions'));
            $fragment->setVar('content1select', $content1select, false);
            $fragment->setVar('content1iframe', $content1iframe, false);
            $fragment->setVar('content2select', $content2select, false);
            $fragment->setVar('content2iframe', $content2iframe, false);

            echo $fragment->parse('core/structure/history/layer.php');
            exit;
    }

    Extension::register('STRUCTURE_CONTENT_HEADER', static function (ExtensionPoint $ep) {
        if ('content/edit' == $ep->getParam('page')) {
            $articleLink = Url::article(Article::getCurrentId(), Language::getCurrentId());
            if (str_starts_with($articleLink, 'http')) {
                $user = Core::requireUser();
                $userLogin = $user->getLogin();
                $historyValidTime = new DateTime();
                $historyValidTime = $historyValidTime->modify('+10 Minutes')->format('YmdHis'); // 10 minutes valid key
                $userHistorySession = HistoryLogin::createSessionKey($userLogin, $user->getValue('session_id'), $historyValidTime);
                $articleLink = Url::article(Article::getCurrentId(), Language::getCurrentId(), [
                    'rex_history_login' => $userLogin,
                    'rex_history_session' => $userHistorySession,
                    'rex_history_validtime' => $historyValidTime,
                ]);
            }

            echo '<script nonce="' . Response::getNonce() . '">
                    var history_article_id = ' . Article::getCurrentId() . ';
                    var history_clang_id = ' . Language::getCurrentId() . ';
                    var history_ctype_id = ' . Request::request('ctype', 'int', 0) . ';
                    var history_article_link = "' . escape($articleLink, 'js') . '";
                </script>';
        }
    });
}

if (Core::getConfig('article_work_version', false)) {
    Extension::register('STRUCTURE_CONTENT_HEADER', static function (ExtensionPoint $ep) {
        if ('content/edit' !== $ep->getParam('page')) {
            return null;
        }

        $params = $ep->getParams();
        $articleId = Type::int($params['article_id']);

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

        $params['slice_revision'] = $version;
    });

    Extension::register('STRUCTURE_CONTENT_BEFORE_SLICES', static function (ExtensionPoint $ep) {
        if ('content/edit' !== $ep->getParam('page')) {
            return null;
        }

        $user = Core::requireUser();
        $params = $ep->getParams();
        $articleId = Type::int($params['article_id']);
        $clangId = Type::int($params['clang']);
        $return = Type::string($ep->getSubject());

        $workingVersionEmpty = true;
        $gw = Sql::factory();
        $gw->setQuery(
            'select * from ' . Core::getTablePrefix(
            ) . 'article_slice where article_id=? and clang_id=? and revision=1 LIMIT 1',
            [$articleId, $clangId],
        );
        if ($gw->getRows() > 0) {
            $workingVersionEmpty = false;
        }

        $func = Request::request('rex_version_func', 'string');
        switch ($func) {
            case 'copy_work_to_live':
                if ($workingVersionEmpty) {
                    $return .= Message::error(I18n::msg('version_warning_working_version_to_live'));
                } elseif ($user->hasPerm('version[live_version]')) {
                    if (true === Core::getConfig('article_history', false)) {
                        ArticleSliceHistory::makeSnapshot($articleId, $clangId, 'work_to_live');
                    }

                    ArticleRevision::copyContent(
                        $articleId,
                        $clangId,
                        ArticleRevision::WORK,
                        ArticleRevision::LIVE,
                    );
                    $return .= Message::success(I18n::msg('version_info_working_version_to_live'));

                    $article = Type::instanceOf(Article::get($articleId, $clangId), Article::class);
                    ArticleRevision::setSessionArticleRevision($articleId, ArticleRevision::LIVE);
                    $params['slice_revision'] = ArticleRevision::LIVE;
                    $return = Extension::registerPoint(
                        new ArticleContentUpdated($article, 'work_to_live', $return),
                    );
                }
                break;
            case 'copy_live_to_work':
                ArticleRevision::copyContent(
                    $articleId,
                    $clangId,
                    ArticleRevision::LIVE,
                    ArticleRevision::WORK,
                );
                $return .= Message::success(I18n::msg('version_info_live_version_to_working'));
                ArticleRevision::setSessionArticleRevision($articleId, ArticleRevision::WORK);
                $params['slice_revision'] = ArticleRevision::WORK;
                break;
            case 'clear_work':
                ArticleRevision::clearContent($articleId, $clangId, ArticleRevision::WORK);
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
            'page' => $params['page'],
            'article_id' => $articleId,
            'clang' => $clangId,
            'ctype' => $params['ctype'],
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

        $fragment = new Fragment();
        $fragment->setVar('button_prefix', '<b>' . $currentRevision . '</b>', false);
        $fragment->setVar('items', $items, false);
        $fragment->setVar('toolbar', true);

        if (!$user->hasPerm('version[live_version]')) {
            $fragment->setVar('disabled', true);
        }

        $toolbar .= '<li class="dropdown">' . $fragment->parse('core/dropdowns/dropdown.php') . '</li>';

        if (!$user->hasPerm('version[live_version]')) {
            if ($revision > 0) {
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work']) . '">' . I18n::msg('version_copy_from_liveversion') . '</a></li>';
                $toolbar .= '<li><a href="' . Url::article($articleId, $clangId, ['rex_version' => ArticleRevision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . I18n::msg('version_preview') . '</a></li>';
            }
        } else {
            if ($revision > 0) {
                if (!$workingVersionEmpty) {
                    $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'clear_work']) . '" data-confirm="' . I18n::msg('version_confirm_clear_workingversion') . '">' . I18n::msg('version_clear_workingversion') . '</a></li>';
                    $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_work_to_live']) . '">' . I18n::msg('version_working_to_live') . '</a></li>';
                }
                $toolbar .= '<li><a href="' . Url::article($articleId, $clangId, ['rex_version' => ArticleRevision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . I18n::msg('version_preview') . '</a></li>';
            } else {
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work']) . '" data-confirm="' . I18n::msg('version_confirm_copy_live_to_workingversion') . '">' . I18n::msg('version_copy_live_to_workingversion') . '</a></li>';
            }
        }

        $inverse = ArticleRevision::WORK == $revision;
        $cssClass = ArticleRevision::WORK == $revision ? 'rex-state-inprogress' : 'rex-state-live';

        $return .= View::toolbar('<ul class="nav navbar-nav">' . $toolbar . '</ul>', null, $cssClass, $inverse);

        return $return;
    });
}

// add theme-information to js-variable rex as rex.theme
// (1) System-Settings (2) no systemforced mode: user-mode (3) fallback: "auto"
$user = Core::getUser();
$theme = (string) Core::getProperty('theme');
if ('' === $theme && $user) {
    $theme = (string) $user->getValue('theme');
}
Asset::setJsProperty('theme', $theme ?: 'auto');

if ('content' == Controller::getCurrentPagePart(1)) {
    Asset::addCssFile(Url::coreAssets('css/metainfo.css'));
    Asset::addJsFile(Url::coreAssets('js/metainfo.js'));
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

// Metainfo
Core::setProperty('metainfo_prefixes', ['art_', 'cat_', 'med_', 'clang_']);
Core::setProperty('metainfo_metaTables', [
    'art_' => Core::getTablePrefix() . 'article',
    'cat_' => Core::getTablePrefix() . 'article',
    'med_' => Core::getTablePrefix() . 'media',
    'clang_' => Core::getTablePrefix() . 'clang',
]);

Extension::register('PAGE_CHECKED', MetaInfo::extensionHandler(...));
Extension::register('BACKUP_BEFORE_DB_IMPORT', MetaInfo::cleanup(...));
Extension::register('STRUCTURE_CONTENT_SIDEBAR', function ($ep) {
    $subject = $ep->getSubject();
    $metaSidebar = include Path::core('pages/metainfo.content.php');
    return $metaSidebar . $subject;
});

// ----- INCLUDE ADDONS
include_once Path::core('boot/addons.php');

if (Core::getUser() && Core::getConfig('be_style_compile')) {
    Style::compile();
}

// ----- Prepare AddOn Pages
if (Core::getUser()) {
    Controller::appendPackagePages();
}

$pages = Extension::registerPoint(new ExtensionPoint('PAGES_PREPARED', Controller::getPages()));
Controller::setPages($pages);

// Set Startpage
if ($user = Core::getUser()) {
    if (Core::getProperty('login')->requiresPasswordChange()) {
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
Extension::registerPoint(new ExtensionPoint('PAGE_CHECKED', $page, ['pages' => $pages], true));

if (in_array($page, ['profile', 'login'], true)) {
    Asset::addJsFile(Url::coreAssets('webauthn.js'), [Asset::JS_IMMUTABLE => true]);
}

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
Response::sendPage($CONTENT);
