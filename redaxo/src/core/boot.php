<?php

use Symfony\Component\HttpFoundation\Request;

/**
 * REDAXO main boot file.
 *
 * @var array{HTDOCS_PATH: non-empty-string, BACKEND_FOLDER: non-empty-string, REDAXO: bool, LOAD_PAGE?: bool, PATH_PROVIDER?: object, URL_PROVIDER?: object} $REX
 *          HTDOCS_PATH    [Required] Relative path to htdocs directory
 *          BACKEND_FOLDER [Required] Name of backend folder
 *          REDAXO         [Required] Backend/Frontend flag
 *          LOAD_PAGE      [Optional] Wether the front controller should be loaded or not. Default value is false.
 *          PATH_PROVIDER  [Optional] Custom path provider
 *          URL_PROVIDER   [Optional] Custom url provider
 */

define('REX_MIN_PHP_VERSION', '8.1');

if (version_compare(PHP_VERSION, REX_MIN_PHP_VERSION) < 0) {
    echo 'Ooops, something went wrong!<br>';
    throw new Exception('PHP version >=' . REX_MIN_PHP_VERSION . ' needed!');
}

foreach (['HTDOCS_PATH', 'BACKEND_FOLDER', 'REDAXO'] as $key) {
    if (!isset($REX[$key])) {
        throw new Exception('Missing required global variable $REX[\'' . $key . "']");
    }
}

// start output buffering as early as possible, so we can be sure
// we can set http header whenever we want/need to
ob_start();
ob_implicit_flush(false);

if ('cli' !== PHP_SAPI) {
    // deactivate session cache limiter
    @session_cache_limiter('');
}

ini_set('session.use_strict_mode', '1');

// set arg_separator to get valid html output if session.use_trans_sid is activated
ini_set('arg_separator.output', '&amp;');
// disable html_errors to avoid html in exceptions and log files
if (ini_get('html_errors')) {
    ini_set('html_errors', '0');
}

require_once __DIR__ . '/lib/util/path.php';

if (isset($REX['PATH_PROVIDER']) && is_object($REX['PATH_PROVIDER'])) {
    /** @var rex_path_default_provider */
    $pathProvider = $REX['PATH_PROVIDER'];
} else {
    require_once __DIR__ . '/lib/util/path_default_provider.php';
    $pathProvider = new rex_path_default_provider($REX['HTDOCS_PATH'], $REX['BACKEND_FOLDER'], true);
}

rex_path::init($pathProvider);

require_once rex_path::core('lib/autoload.php');

// register core-classes as php-handlers
rex_autoload::register();
// add core base-classpath to autoloader
rex_autoload::addDirectory(rex_path::core('lib'));

// must be called after `rex_autoload::register()` to support symfony/polyfill-mbstring
mb_internal_encoding('UTF-8');

if (isset($REX['URL_PROVIDER']) && is_object($REX['URL_PROVIDER'])) {
    /** @var rex_path_default_provider */
    $urlProvider = $REX['URL_PROVIDER'];
} else {
    $urlProvider = new rex_path_default_provider($REX['HTDOCS_PATH'], $REX['BACKEND_FOLDER'], false);
}

rex_url::init($urlProvider);

// start timer at the very beginning
rex::setProperty('timer', new rex_timer($_SERVER['REQUEST_TIME_FLOAT'] ?? null));
// add backend flag to rex
rex::setProperty('redaxo', $REX['REDAXO']);
// add core lang directory to rex_i18n
rex_i18n::addDirectory(rex_path::core('lang'));
// add core base-fragmentpath to fragmentloader
rex_fragment::addDirectory(rex_path::core('fragments/'));

// ----------------- FUNCTIONS
require_once rex_path::core('functions/function_rex_escape.php');
require_once rex_path::core('functions/function_rex_globals.php');
require_once rex_path::core('functions/function_rex_other.php');

// ----------------- VERSION
rex::setProperty('version', '5.20.2');

rex_error_handler::register();

rex::loadConfigYml();

date_default_timezone_set(rex::getProperty('timezone', 'Europe/Berlin'));

if ('cli' !== PHP_SAPI) {
    rex::setProperty('request', Request::createFromGlobals());
}

rex_var_dumper::register();

// ----------------- REX PERMS

rex_complex_perm::register('clang', rex_clang_perm::class);

// ----- SET CLANG
if (!rex::isSetup()) {
    $clangId = rex_request('clang', 'int', rex_clang::getStartId());
    if (rex::isBackend() || rex_clang::exists($clangId)) {
        rex_clang::setCurrentId($clangId);
    }
}

// ----------------- HTTPS REDIRECT
if ('cli' !== PHP_SAPI && !rex::isSetup()) {
    if ((true === rex::getProperty('use_https') || rex::getEnvironment() === rex::getProperty('use_https')) && !rex_request::isHttps()) {
        rex_response::enforceHttps();
    }

    if (true === rex::getProperty('use_hsts') && rex_request::isHttps()) {
        rex_response::setHeader('Strict-Transport-Security', 'max-age=' . (int) rex::getProperty('hsts_max_age', 31536000)); // default 1 year
    }
}

rex_extension::register('SESSION_REGENERATED', [rex_backend_login::class, 'sessionRegenerated']);

if (isset($REX['LOAD_PAGE']) && $REX['LOAD_PAGE']) {
    unset($REX);
    require rex_path::core(rex::isBackend() ? 'backend.php' : 'frontend.php');
}
