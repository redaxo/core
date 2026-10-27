<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentRendering;
use Redaxo\Core\Content\ExtensionPoint\ArticleSlicesQuery;
use Redaxo\Core\Content\HistoryLogin;
use Redaxo\Core\Core;
use Redaxo\Core\Env;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Exception\HttpException;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Log\ErrorReport;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\View\Fragment;

if (Core::isDevMode()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

if (Env::get('REX_ERROR_EMAIL')) {
    Extension::register('RESPONSE_SHUTDOWN', static function () {
        ErrorReport::send();
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
                    Core::setUser($user);

                    // A shutdown function (not an OUTPUT_FILTER) so cleanup runs even when the request aborts
                    // before output — e.g. a bogus rex-api-call — which would otherwise leave a usable session.
                    register_shutdown_function(static function () use ($login) {
                        $login->deleteSession();
                        UserSession::getInstance()->clearCurrentSession();
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

        Extension::register(ArticleContentRendering::class, static function (ArticleContentRendering $ep): void {
            // Render the requested article live from the database instead of the published content cache.
            $ep->content->eval = true;
        });

        // The article specific permissions are checked where the slices of the history are about to be fetched.
        Extension::register(ArticleSlicesQuery::class, static function (ArticleSlicesQuery $ep) use ($user): ?string {
            $historyDate = Request::request('rex_history_date', 'string');
            $article = $ep->content;

            if ($article instanceof ArticleContent && $article->articleId == Article::getCurrentId()) {
                $historyArticle = Article::get($article->articleId, $article->languageId);
                if (
                    !$historyArticle instanceof Article
                    || !$user->getComplexPerm('language')->hasPerm($article->languageId)
                    || !$user->getComplexPerm('structure')->hasCategoryPerm($historyArticle->categoryId)
                ) {
                    throw new HttpException('No permission for the history of this article.', Response::HTTP_FORBIDDEN);
                }

                return ArticleSliceHistory::getSlicesQuery($historyDate, $article->articleId, $article->languageId);
            }

            return null;
        });
    }
}

if (Core::getConfig('article_work_version', false)) {
    Extension::register(ArticleContentRendering::class, static function (ArticleContentRendering $ep): void {
        $version = Request::request('rex_version', 'int');
        if (ArticleRevision::WORK != $version) {
            return;
        }

        // createUser() instead of hasSession(): it validates that the session is still valid and that the user
        // still exists and is active
        if (!BackendLogin::createUser()) {
            $fragment = new Fragment([
                'content' => '<p>No permission for the working version. You need to be logged into the REDAXO backend at the same time.</p>',
            ]);
            Response::setStatus(Response::HTTP_UNAUTHORIZED);
            Response::sendPage($fragment->parse('core/fe_ooops.php'));
            exit;
        }

        // Render the working version live from the database instead of the published content cache.
        $ep->content->sliceRevision = $version;
        $ep->content->eval = true;
    });

    // The article specific permissions are checked where the slices of the working version are about to be fetched.
    Extension::register(ArticleSlicesQuery::class, static function (ArticleSlicesQuery $ep): ?string {
        if (ArticleRevision::WORK != Request::request('rex_version', 'int')) {
            return null;
        }

        $article = $ep->content;
        if (!$article instanceof ArticleContent || $article->articleId !== Article::getCurrentId()) {
            return null;
        }

        $user = BackendLogin::createUser();
        $previewArticle = Article::get($article->articleId, $article->languageId);

        if (
            !$user
            || !$previewArticle instanceof Article
            || !$user->getComplexPerm('language')->hasPerm($article->languageId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($previewArticle->categoryId)
        ) {
            throw new HttpException('No permission for the working version of this article.', Response::HTTP_FORBIDDEN);
        }

        return null;
    });
}

$languageId = Request::get('language', 'int');
if ($languageId && !Language::exists($languageId)) {
    Response::sendRedirect(Url::article(Article::getNotfoundArticleId(), Language::getStartId()));
}

try {
    $article = new ArticleContent(Article::getCurrentId());
} catch (ArticleNotFoundException) {
    if (!Core::isDevMode() && !BackendLogin::hasSession()) {
        throw new NotFoundHttpException('Article with id ' . Article::getCurrentId() . ' does not exist.');
    }

    $fragment = new Fragment([
        'content' => '<p><b>Article with ID ' . Article::getCurrentId() . ' not found.</b><br />If this is a fresh setup, an article must be created first.<br />Enter <a href="' . Url::backendController() . '">REDAXO</a>.</p>',
    ]);
    $content .= $fragment->parse('core/fe_ooops.php');
    Response::sendPage($content);
    exit;
}

Extension::dispatch(new ArticleContentRendering($article));

try {
    $content .= $article->renderTemplate();
} catch (ArticleNotFoundException) {
    // The error article is a fallback and is always rendered normally from the published content cache,
    // regardless of any history/work-version mode the failed request might have been in.
    $article = new ArticleContent(Article::getNotfoundArticleId());
    Article::setCurrentId(Article::getNotfoundArticleId());

    $content .= $article->renderTemplate();
}

$artId = $article->articleId;
if ($artId == Article::getNotfoundArticleId() && $artId != Article::getSiteStartArticleId()) {
    Response::setStatus(Response::HTTP_NOT_FOUND);
}

// ----- inhalt ausgeben
Response::sendPage($content);
