<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_status_change')]
class ArticleStatusChange extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('publishArticle[]')) {
            throw new ApiFunctionException('User has no permission to publish articles!');
        }

        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');
        $status = Request::request('art_status', 'int', null);

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

        ArticleHandler::articleStatus($articleId, $clang, $status);

        return new Result(true, I18n::msg('article_status_updated'));
    }
}
