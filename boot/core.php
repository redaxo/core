<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\CronjobExecutor;
use Redaxo\Core\Cronjob\CronjobManager;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\DefaultPathProvider;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguagePermission;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Util\VarDumper;
use Redaxo\Core\View\Fragment;
use Symfony\Component\HttpFoundation\Request as BaseRequest;

/**
 * REDAXO main boot file.
 *
 * @var array{REDAXO: bool, PATH_PROVIDER: DefaultPathProvider, URL_PROVIDER: DefaultPathProvider} $REX
 *          REDAXO         [Required] Backend/Frontend flag
 *          PATH_PROVIDER  [Required] Path provider
 *          URL_PROVIDER   [Required] Url provider
 */

define('REX_MIN_PHP_VERSION', '8.5');

if (version_compare(PHP_VERSION, REX_MIN_PHP_VERSION) < 0) {
    echo 'Ooops, something went wrong!<br>';
    throw new Exception('PHP version >=' . REX_MIN_PHP_VERSION . ' needed!');
}

foreach (['REDAXO', 'PATH_PROVIDER', 'URL_PROVIDER'] as $key) {
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

ini_set('arg_separator.output', '&');
// disable html_errors to avoid html in exceptions and log files
if (ini_get('html_errors')) {
    ini_set('html_errors', '0');
}

// must be called after autoloader to support symfony/polyfill-mbstring
mb_internal_encoding('UTF-8');

Path::init($REX['PATH_PROVIDER']);
Url::init($REX['URL_PROVIDER']);

// start timer at the very beginning
Core::setProperty('timer', new Timer($_SERVER['REQUEST_TIME_FLOAT'] ?? null));
// add backend flag to rex
Core::setProperty('redaxo', $REX['REDAXO']);
// add core lang directory to I18n
I18n::addDirectory(Path::core('lang'));
// add core base-fragmentpath to fragmentloader
Fragment::addDirectory(Path::core('fragments/'));

ErrorHandler::register();

// Derive APP_ENV from REX_MODE for third-party code keying off Symfony's conventional env var
// (dev => "dev", live/hardened => "prod"); APP_DEBUG is derived from REX_MODE by the runtime already.
// A pre-defined APP_ENV is overridden deliberately: REX_MODE is the single source of truth, and a stray
// APP_ENV (hosting panel default, copied Symfony .env) must not leak a different environment.
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = Core::isDevMode() ? 'dev' : 'prod';

Core::loadConfigYml();

Core::getProject()->configure();

// in setup the locale comes from the request (there may be no database yet), in the console it is always english
if (!Core::isSetup() && 'cli' !== PHP_SAPI) {
    I18n::$defaultLocale = Type::string(Core::getConfig('lang', I18n::$defaultLocale));
}

date_default_timezone_set(Core::getProperty('timezone', 'Europe/Berlin'));

if ('cli' !== PHP_SAPI) {
    Core::setProperty('request', BaseRequest::createFromGlobals());
}

VarDumper::register();

// ----------------- REX PERMS

ComplexPermission::register('clang', LanguagePermission::class);
ComplexPermission::register('structure', StructurePermission::class);
ComplexPermission::register('modules', ModulePermission::class);
ComplexPermission::register('media', MediaPoolPermission::class);

// ----- SET CLANG
if (!Core::isSetup()) {
    $clangId = Request::request('clang', 'int', Language::getStartId());
    if (Core::isBackend() || Language::exists($clangId)) {
        Language::setCurrentId($clangId);
    }
}

// ----------------- HTTPS REDIRECT
if ('cli' !== PHP_SAPI && !Core::isSetup()) {
    if ((true === Core::getProperty('use_https') || Core::getEnvironment()->value === Core::getProperty('use_https')) && !Request::isHttps()) {
        Response::enforceHttps();
    }

    if (true === Core::getProperty('use_hsts') && Request::isHttps()) {
        Response::setHeader('Strict-Transport-Security', 'max-age=' . (int) Core::getProperty('hsts_max_age', 31536000)); // default 1 year
    }
}

$nexttime = Core::isSetup() || 'cli' === PHP_SAPI ? 0 : (int) Core::getConfig('cronjob_nexttime', 0);
if (0 !== $nexttime && time() >= $nexttime) {
    $env = CronjobExecutor::getCurrentEnvironment();
    $EP = 'backend' === $env ? 'PAGE_CHECKED' : 'PACKAGES_INCLUDED';
    Extension::register($EP, static function () use ($env) {
        if ('backend' !== $env || !in_array(Controller::getCurrentPagePart(1), ['setup', 'login', 'cronjob'], true)) {
            CronjobManager::factory()->check();
        }
    });
}

if (!Core::isSetup()) {
    Core::setProperty('start_article_id', Core::getConfig('start_article_id', 1));
    Core::setProperty('notfound_article_id', Core::getConfig('notfound_article_id', 1));
    Core::setProperty('rows_per_page', 50);

    if (0 == Request::request('article_id', 'int')) {
        Core::setProperty('article_id', Article::getSiteStartArticleId());
    } else {
        $articleId = Request::request('article_id', 'int');
        $articleId = Article::get($articleId) ? $articleId : Article::getNotfoundArticleId();
        Core::setProperty('article_id', $articleId);
    }

    if (Core::getConfig('article_history', false)) {
        Extension::register(
            ['ART_SLICES_COPY', 'SLICE_ADD', 'SLICE_UPDATE', 'SLICE_MOVE', 'SLICE_DELETE'],
            static function (ExtensionPoint $ep) {
                $type = match ($ep->name) {
                    'ART_SLICES_COPY' => 'slices_copy',
                    'SLICE_MOVE' => 'slice_' . $ep->getParam('direction'),
                    default => strtolower($ep->name),
                };

                $articleId = $ep->getParam('article_id');
                $clangId = $ep->getParam('clang_id');
                $sliceRevision = $ep->getParam('slice_revision');

                if (0 == $sliceRevision) {
                    ArticleSliceHistory::makeSnapshot($articleId, $clangId, $type);
                }
            },
        );
    }
}
