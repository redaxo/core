<?php

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");

// assets which are passed with a cachebuster will be cached very long,
// as we assume their url will change when the underlying content changes
if (rex_get('asset') && rex_get('buster')) {
    /** @psalm-taint-escape file */ // it is not escaped here, but it is validated below via the realpath
    $assetFile = rex_get('asset', 'string');

    // relative to the assets-root
    if (str_starts_with($assetFile, '/assets/')) {
        $assetFile = '..' . $assetFile;
    }

    $fullPath = realpath($assetFile);
    $assetDir = rex_path::assets();

    if (!$fullPath) {
        throw new rex_http_exception(new Exception('File "' . $assetFile . '" not found'), rex_response::HTTP_NOT_FOUND);
    }
    if (!str_starts_with($fullPath, $assetDir)) {
        throw new rex_http_exception(new Exception('Assets can only be streamed from within the assets folder. "' . $fullPath . '" is not within "' . $assetDir . '"'), rex_response::HTTP_NOT_FOUND);
    }

    $ext = rex_file::extension($assetFile);
    if (!in_array($ext, ['js', 'css'], true)) {
        throw new rex_http_exception(new Exception('Only JS and CSS files can be streamed from the assets folder'), rex_response::HTTP_NOT_FOUND);
    }

    $content = rex_file::get($assetFile);
    if (null === $content) {
        throw new rex_http_exception(new Exception('File "' . $assetFile . '" not found'), rex_response::HTTP_NOT_FOUND);
    }

    if ('js' === $ext) {
        $js = preg_replace('@^//# sourceMappingURL=.*$@m', '', $content);

        rex_response::sendCacheControl('max-age=31536000, immutable');
        rex_response::sendContent($js, 'application/javascript');
    } else {
        // If we are in a directory off the root, add a relative path here back to the root, like "../"
        // get the public path to this file, plus the baseurl
        $relativeroot = '';
        $pubroot = dirname($_SERVER['PHP_SELF']) . '/' . $relativeroot;

        $prefix = $pubroot . dirname($assetFile) . '/';
        $styles = preg_replace('/(url\(["\']?)([^\/"\'])([^\:\)]+["\']?\))/i', '$1' . $prefix . '$2$3', $content);

        rex_response::sendCacheControl('max-age=31536000, immutable');
        rex_response::sendContent($styles, 'text/css');
    }
    exit;
}

// ----- verfuegbare seiten
$pages = [];

// ----------------- SETUP
if (rex::isSetup()) {
    // ----------------- SET SETUP LANG
    $requestLang = rex_request('lang', 'string', rex::getProperty('lang'));
    if (in_array($requestLang, rex_i18n::getLocales())) {
        rex::setProperty('lang', $requestLang);
    } else {
        rex::setProperty('lang', 'en_gb');
    }

    rex_i18n::setLocale(rex::getProperty('lang'));

    $pages['setup'] = rex_be_controller::getSetupPage();
    rex_be_controller::setCurrentPage('setup');
} else {
    // ----------------- CREATE LANG OBJ
    rex_i18n::setLocale(rex::getProperty('lang'));

    // ---- prepare login
    $login = new rex_backend_login();
    rex::setProperty('login', $login);

    $passkey = rex_post('rex_user_passkey', 'string', null);
    $rexUserLogin = rex_post('rex_user_login', 'string');
    $rexUserPsw = rex_post('rex_user_psw', 'string');
    $rexUserStayLoggedIn = rex_post('rex_user_stay_logged_in', 'boolean', false);

    if (rex_get('rex_logout', 'boolean') && rex_csrf_token::factory('backend_logout')->isValid()) {
        $login->setLogout(true);
        $login->checkLogin();
        rex_csrf_token::removeAll();

        // "cache" is deliberately omitted, see the comment on the login page below.
        rex_response::setHeader('Clear-Site-Data', '"storage", "executionContexts"');

        // Not all browsers support the header Clear-Site-Data.
        // we dont kill/regenerate the session so e.g. the frontend will not get logged out
        rex_request::clearSession();

        // is necessary for login after logout
        // and without the redirect, the csrf token would be invalid
        rex_response::sendRedirect(rex_url::backendController(['rex_logged_out' => 1]));
    }

    $rexUserLoginmessage = '';

    if (($rexUserLogin || $passkey) && !rex_csrf_token::factory('backend_login')->isValid()) {
        $loginCheck = rex_i18n::msg('csrf_token_invalid');
    } else {
        // the server side encryption of pw is only required
        // when not already encrypted by client using javascript
        $login->setLogin($rexUserLogin, $rexUserPsw, rex_post('javascript', 'boolean'));
        $login->setPasskey('' === $passkey ? null : $passkey);
        $login->setStayLoggedIn($rexUserStayLoggedIn);
        $loginCheck = $login->checkLogin();
    }

    if (true !== $loginCheck) {
        if (rex_request::isXmlHttpRequest()) {
            rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
        }

        // login failed
        $rexUserLoginmessage = $login->getMessage();

        // Fehlermeldung von der Datenbank
        if (is_string($loginCheck)) {
            $rexUserLoginmessage = $loginCheck;
        }

        $pages['login'] = rex_be_controller::getLoginPage();
        rex_be_controller::setCurrentPage('login');

        if ('login' !== rex_request('page', 'string', 'login')) {
            // clear in-browser data of a previous session with the same browser for security reasons.
            // a possible attacker should not be able to access cached data of a previous valid session on the same computer.
            // clearing "executionContext" or "cookies" would result in a endless loop.
            //
            // "cache" is deliberately omitted: browsers hold back the response until the entire HTTP cache
            // has been walked to filter it by origin, which takes seconds on a well-filled profile and
            // therefore delays every backend request made with an expired session.
            // It also buys very little, because backend pages are sent with "no-cache, max-age=0, private"
            // anyway, while the clearing would additionally wipe the cache of the website on the same origin.
            rex_response::setHeader('Clear-Site-Data', '"storage"');

            // Not all browsers support the header Clear-Site-Data.
            // we dont kill/regenerate the session so e.g. the frontend will not get logged out
            rex_request::clearSession();
        }
    } else {
        // Userspezifische Sprache einstellen
        $user = $login->getUser();
        $lang = $user->getLanguage();
        if ($lang && 'default' != $lang && $lang != rex::getProperty('lang')) {
            rex_i18n::setLocale($lang);
        }

        rex::setProperty('user', $user);

        // Safe Mode
        if (!rex::isLiveMode() && $user->isAdmin() && null !== ($safeMode = rex_get('safemode', 'boolean', null))) {
            if ($safeMode) {
                rex_set_session('safemode', true);
            } else {
                rex_unset_session('safemode');
                if (rex::getProperty('safe_mode')) {
                    $configFile = rex_path::coreData('config.yml');
                    $config = array_merge(
                        rex_file::getConfig(rex_path::core('default.config.yml')),
                        rex_file::getConfig($configFile),
                    );
                    $config['safe_mode'] = false;
                    rex_file::putConfig($configFile, $config);
                }
            }
        }
    }

    if ('' === $rexUserLoginmessage && rex_get('rex_logged_out', 'boolean')) {
        $rexUserLoginmessage = rex_i18n::msg('login_logged_out');
    }
}

rex_be_controller::setPages($pages);

// ----- Prepare Core Pages
if (rex::getUser()) {
    rex_be_controller::setCurrentPage(trim(rex_request('page', 'string')));
    rex_be_controller::appendLoggedInPages();

    if ('profile' !== rex_be_controller::getCurrentPage() && rex::getProperty('login')->requiresPasswordChange()) {
        rex_response::sendRedirect(rex_url::backendPage('profile'));
    }
}

rex_view::addJsFile(rex_url::coreAssets('jquery.min.js'), [rex_view::JS_IMMUTABLE => true]);
rex_view::addJsFile(rex_url::coreAssets('jquery-ui.custom.min.js'), [rex_view::JS_IMMUTABLE => true]);
rex_view::addJsFile(rex_url::coreAssets('jquery-pjax.min.js'), [rex_view::JS_IMMUTABLE => true]);
rex_view::addJsFile(rex_url::coreAssets('standard.js'), [rex_view::JS_IMMUTABLE => true]);
rex_view::addJsFile(rex_url::coreAssets('sha1.js'), [rex_view::JS_IMMUTABLE => true]);
rex_view::addJsFile(rex_url::coreAssets('clipboard-copy-element.js'), [rex_view::JS_IMMUTABLE => true]);

rex_view::setJsProperty('backend', true);
rex_view::setJsProperty('accesskeys', rex::getProperty('use_accesskeys'));
rex_view::setJsProperty('directory_protection_check', rex::getProperty('use_directory_protection_check'));

if (rex::getUser()) {
    rex_view::addJsFile(rex_url::coreAssets('session-timeout.js'), [rex_view::JS_IMMUTABLE => true]);

    $login = rex::getProperty('login');
    rex_view::setJsProperty('session_keep_alive_url', rex_url::backendController(['page' => 'credits', 'rex-api-call' => 'user_session_status'], false));
    rex_view::setJsProperty('session_logout_url', rex_url::backendController(['rex_logout' => 1] + rex_csrf_token::factory('backend_logout')->getUrlParams(), false));
    rex_view::setJsProperty('session_login_url', rex_url::backendController([], false));
    rex_view::setJsProperty('session_keep_alive', rex::getProperty('session_keep_alive', 0));
    rex_view::setJsProperty('session_duration', rex::getProperty('session_duration', 0));
    rex_view::setJsProperty('session_max_overall_duration', rex::getProperty('session_max_overall_duration', 0));
    rex_view::setJsProperty('session_start', $login->getSessionVar(rex_login::SESSION_START_TIME));
    rex_view::setJsProperty('session_stay_logged_in', $login->getSessionVar(rex_backend_login::SESSION_STAY_LOGGED_IN, false));
    rex_view::setJsProperty('session_warning_time', rex::getProperty('session_warning_time', 300));
    rex_view::setJsProperty('session_server_time', time());

    rex_view::setJsProperty('i18n', [
        'session_timeout_title' => rex_i18n::msg('session_timeout_title'),
        'session_timeout_message_expand' => rex_i18n::msg('session_timeout_message_expand'),
        'session_timeout_message_expired' => rex_i18n::msg('session_timeout_message_expired'),
        'session_timeout_message_has_expired' => rex_i18n::msg('session_timeout_message_has_expired'),
        'session_timeout_logout_label' => rex_i18n::msg('session_timeout_logout_label'),
        'session_timeout_login_label' => rex_i18n::msg('session_timeout_login_label'),
        'session_timeout_refresh_label' => rex_i18n::msg('session_timeout_refresh_label'),
    ]);
}

rex_view::setJsProperty('cookie_params', rex_login::getCookieParams());

// ----- INCLUDE ADDONS
include_once rex_path::core('packages.php');

// ----- Prepare AddOn Pages
if (rex::getUser()) {
    rex_be_controller::appendPackagePages();
}

$pages = rex_extension::registerPoint(new rex_extension_point('PAGES_PREPARED', rex_be_controller::getPages()));
rex_be_controller::setPages($pages);

// Set Startpage
if ($user = rex::getUser()) {
    if (rex::getProperty('login')->requiresPasswordChange()) {
        // profile is available for everyone, no additional checks required
        rex_be_controller::setCurrentPage('profile');
    } elseif (!rex_be_controller::getCurrentPage()) {
        // trigger api functions before page permission check/redirection, if page param is not set.
        // the api function is responsible for checking permissions.
        rex_api_function::handleCall();
    }

    // --- page pruefen und benoetigte rechte checken
    rex_be_controller::checkPagePermissions($user);
}
$page = rex_be_controller::getCurrentPage();
rex_view::setJsProperty('page', $page);

// ----- EXTENSION POINT
// page variable validated
rex_extension::registerPoint(new rex_extension_point('PAGE_CHECKED', $page, ['pages' => $pages], true));

if (in_array($page, ['profile', 'login'], true)) {
    rex_view::addJsFile(rex_url::coreAssets('webauthn.js'), [rex_view::JS_IMMUTABLE => true]);
}

if ($page) {
    // trigger api functions after PAGE_CHECKED, if page param is set
    // the api function is responsible for checking permissions.
    rex_api_function::handleCall();
}

// include the requested backend page
rex_be_controller::includeCurrentPage();

// ----- caching end für output filter
$CONTENT = ob_get_clean();

// ----- inhalt ausgeben
rex_response::sendPage($CONTENT);
