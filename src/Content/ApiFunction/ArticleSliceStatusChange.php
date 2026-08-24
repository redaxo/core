<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_slice_status_change')]
final class ArticleSliceStatusChange extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('publishSlice[]')) {
            throw new ApiFunctionException('User has no permission to publish slices!');
        }

        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');

        $article = Article::get($articleId, $clang);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->categoryId)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        $sliceId = Request::request('slice_id', 'int');
        $status = Request::request('status', 'int');

        // the slice must belong to the article whose category permission was checked above, otherwise slices of
        // categories the user has no permission for could be addressed
        $slice = Sql::factory();
        $slice->setQuery('SELECT id FROM ' . Core::getTable('article_slice') . ' WHERE id = ? AND article_id = ? AND clang_id = ?', [$sliceId, $articleId, $clang]);
        if (1 !== $slice->getRows()) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        ContentHandler::sliceStatus($sliceId, $status);

        return new Result(true);
    }
}
