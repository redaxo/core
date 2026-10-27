<?php

use Redaxo\Core\Backend\ArticleHistory;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\HistoryLogin;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

/**
 * Article history, opened in an overlay from the content page; included by content.php before the editor is built.
 *
 * @var Article $OOArt
 * @var int $articleId
 * @var int $languageId
 * @var int $categoryId
 */

echo View::title(I18n::msg('structure_history') . ': ' . escape($OOArt->name), '');

$user = Core::requireUser();
if (
    !$user->getComplexPerm('language')->hasPerm($languageId)
    || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
) {
    echo Message::warning(I18n::msg('no_rights_to_edit'));
    return;
}

// restoring a version replaces the live revision
$canRestore = ContentHandler::hasRevisionPerm($user, ArticleRevision::LIVE);

if ('restore' === Request::post('func', 'string')) {
    if (!$canRestore) {
        echo Message::error(I18n::msg('no_rights_to_this_function'));
    } elseif (!CsrfToken::factory('structure_history')->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
    } elseif (!ArticleSliceHistory::restoreSnapshot(Request::post('history_date', 'string'), $articleId, $languageId)) {
        echo Message::error(I18n::msg('structure_history_snapshot_not_found'));
    } else {
        Response::sendRedirect(Url::backendPage('content/edit', [
            'article_id' => $articleId,
            'category_id' => $categoryId,
            'language' => $languageId,
            'info' => I18n::msg('structure_history_snapshot_reactivated'),
        ]));
    }
}

$previewUrl = Url::article($articleId, $languageId);
// on another domain the frontend lacks the backend session: a key valid for 10 minutes logs the previews in
if (str_starts_with($previewUrl, 'http')) {
    $validTime = new DateTimeImmutable('+10 minutes')->format('YmdHis');
    $previewUrl = Url::article($articleId, $languageId, [
        'rex_history_login' => $user->login,
        'rex_history_session' => HistoryLogin::createSessionKey($user->login, $validTime),
        'rex_history_validtime' => $validTime,
    ]);
}

echo new ArticleHistory(
    previewUrl: $previewUrl,
    snapshots: ArticleSliceHistory::getSnapshots($articleId, $languageId),
    workVersion: true === Core::getConfig('article_work_version', false),
    restoreUrl: $canRestore ? Url::currentBackendPage(['article_id' => $articleId, 'language' => $languageId]) : null,
)->render();
