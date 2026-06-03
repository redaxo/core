<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_slice_move')]
final class ArticleSliceMove extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('moveSlice[]')) {
            throw new ApiFunctionException('User has no permission to move slices!');
        }

        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');
        $sliceId = Request::request('slice_id', 'int');
        $direction = Request::request('direction', 'string');

        $article = Article::get($articleId, $clang);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        $CM = Sql::factory();
        $CM->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice where id=? and clang_id=?', [$sliceId, $clang]);
        if (1 != $CM->getRows()) {
            throw new ApiFunctionException(I18n::msg('module_not_found'));
        }

        $moduleKey = (string) $CM->getValue(Core::getTablePrefix() . 'article_slice.module');
        if (!Module::exists($moduleKey)) {
            throw new ApiFunctionException(I18n::msg('module_not_found'));
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->categoryId)
            || !$user->getComplexPerm('modules')->hasPerm($moduleKey)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        $message = ContentHandler::moveSlice($sliceId, $clang, $direction);

        return new Result(true, $message);
    }
}
