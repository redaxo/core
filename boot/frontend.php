<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Content\HistoryLogin;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Exception\HttpException;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;

if (Core::isSetup()) {
    Response::sendRedirect(Url::backendController());
}

if (Core::isDebugMode()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

if (0 != Core::getConfig('phpmailer_errormail')) {
    Extension::register('RESPONSE_SHUTDOWN', static function () {
        Mailer::errorMail();
    });
}

// ----- INCLUDE ADDONS
include_once Path::core('boot/addons.php');

// ----- caching end für output filter
$content = ob_get_clean();

// trigger api functions. the api function is responsible for checking permissions.
ApiFunction::handleCall();

if (Extension::hasExtensions('FE_OUTPUT')) {
    // ----- EXTENSION POINT
    Extension::dispatch(new ExtensionPoint('FE_OUTPUT', $content));

    return;
}

if (Core::getConfig('article_history', false)) {
    $historyDate = Request::request('rex_history_date', 'string');

    if ('' != $historyDate) {
        $historySession = Request::request('rex_history_session', 'string');
        $historyLogin = Request::request('rex_history_login', 'string');
        $historyValidtime = Request::request('rex_history_validtime', 'string');

        $user = null;
        if ('' != $historySession && '' != $historyLogin && '' != $historyValidtime) {
            $validtill = DateTime::createFromFormat('YmdHis', $historyValidtime);
            $now = new DateTime();
            if ($now < $validtill) {
                $login = new HistoryLogin();

                if ($login->checkTempSession($historyLogin, $historySession, $historyValidtime)) {
                    $user = $login->getUser();
                    Core::setProperty('user', $user);
                    Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep) use ($login) {
                        $login->deleteSession();
                    });
                }
            }
        } else {
            $user = BackendLogin::createUser();
        }

        if (!$user) {
            throw new HttpException('No permission.', Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasPerm('history[article_rollback]')) {
            throw new HttpException('No permission for the slice version.', Response::HTTP_FORBIDDEN);
        }

        Extension::register('ART_INIT', static function (ExtensionPoint $ep) {
            // Render the requested article live from the database instead of the published content cache.
            Type::instanceOf($ep->subject, ArticleContent::class)->eval = true;
        });

        Extension::register('ART_SLICES_QUERY', static function (ExtensionPoint $ep) {
            $historyDate = Request::request('rex_history_date', 'string');
            $article = $ep->getParam('article');

            if ($article instanceof ArticleContent && $article->articleId == Article::getCurrentId()) {
                $articleLimit = '';
                if (0 != $article->articleId) {
                    $articleLimit = ' AND ' . Core::getTablePrefix() . 'article_slice.article_id=' . $article->articleId;
                }

                ArticleSliceHistory::checkTables();

                $escapeSql = Sql::factory();

                $sliceDate = ' AND ' . Core::getTablePrefix() . 'article_slice.history_date = ' . $escapeSql->escape($historyDate);

                return 'SELECT ' . Core::getTablePrefix() . 'article_slice.*, ' . Core::getTablePrefix() . 'article.parent_id
                    FROM
                        ' . ArticleSliceHistory::getTable() . ' as ' . Core::getTablePrefix() . 'article_slice
                    LEFT JOIN ' . Core::getTablePrefix() . 'article ON ' . Core::getTablePrefix() . 'article_slice.article_id=' . Core::getTablePrefix() . 'article.id
                    WHERE
                        ' . Core::getTablePrefix() . "article_slice.clang_id='" . $article->clangId . "' AND
                        " . Core::getTablePrefix() . "article.clang_id='" . $article->clangId . "' AND
                        " . Core::getTablePrefix() . 'article_slice.revision=0
                        ' . $articleLimit . '
                        ' . $sliceDate . '
                        ORDER BY ' . Core::getTablePrefix() . 'article_slice.priority';
            }

            return null;
        });
    }
}

if (Core::getConfig('article_work_version', false)) {
    Extension::register('ART_INIT', static function (ExtensionPoint $ep) {
        $version = Request::request('rex_version', 'int');
        if (ArticleRevision::WORK != $version) {
            return;
        }

        if (!BackendLogin::hasSession()) {
            $fragment = new Fragment([
                'content' => '<p>No permission for the working version. You need to be logged into the REDAXO backend at the same time.</p>',
            ]);
            Response::setStatus(Response::HTTP_UNAUTHORIZED);
            Response::sendPage($fragment->parse('core/fe_ooops.php'));
            exit;
        }

        // Render the working version live from the database instead of the published content cache.
        $article = Type::instanceOf($ep->subject, ArticleContent::class);
        $article->sliceRevision = $version;
        $article->eval = true;
    });
}

$clangId = Request::get('clang', 'int');
if ($clangId && !Language::exists($clangId)) {
    Response::sendRedirect(Url::article(Article::getNotfoundArticleId(), Language::getStartId()));
}

try {
    $article = new ArticleContent(Article::getCurrentId());
} catch (ArticleNotFoundException) {
    if (!Core::isDebugMode() && !BackendLogin::hasSession()) {
        throw new NotFoundHttpException('Article with id ' . Article::getCurrentId() . ' does not exist.');
    }

    $fragment = new Fragment([
        'content' => '<p><b>Article with ID ' . Article::getCurrentId() . ' not found.</b><br />If this is a fresh setup, an article must be created first.<br />Enter <a href="' . Url::backendController() . '">REDAXO</a>.</p>',
    ]);
    $content .= $fragment->parse('core/fe_ooops.php');
    Response::sendPage($content);
    exit;
}

// ----- EXTENSION POINT: lets extensions configure the requested article content object,
// e.g. to render the history or working version live from the database instead of the cache.
Extension::dispatch(new ExtensionPoint('ART_INIT', $article, [
    'article_id' => Article::getCurrentId(),
    'clang' => Language::getCurrentId(),
], readonly: true));

try {
    $content .= $article->getArticleTemplate();
} catch (ArticleNotFoundException) {
    // The error article is a fallback and is always rendered normally from the published content cache,
    // regardless of any history/work-version mode the failed request might have been in.
    $article = new ArticleContent(Article::getNotfoundArticleId());
    Core::setProperty('article_id', Article::getNotfoundArticleId());

    $content .= $article->getArticleTemplate();
}

$artId = $article->articleId;
if ($artId == Article::getNotfoundArticleId() && $artId != Article::getSiteStartArticleId()) {
    Response::setStatus(Response::HTTP_NOT_FOUND);
}

// ----- inhalt ausgeben
Response::sendPage($content);
