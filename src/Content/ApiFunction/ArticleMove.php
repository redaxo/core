<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_move')]
final class ArticleMove extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('moveArticle[]')) {
            throw new ApiFunctionException('User has no permission to move articles!');
        }

        // The article to move
        $articleId = Request::request('article_id', 'int');
        // The destination category in which the given article will be moved
        $categoryIdNew = Request::request('category_id_new', 'int');

        $article = Article::get($articleId);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '"!');
        }

        if (0 !== $categoryIdNew && null === Category::get($categoryIdNew)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryIdNew . '"!');
        }

        $categoryId = $article->categoryId ?? 0;

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryIdNew)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if (ArticleHandler::moveArticle($articleId, $categoryId, $categoryIdNew)) {
            return new Result(true, I18n::msg('content_articlemoved'));
        }

        return new Result(false, I18n::msg('content_errormovearticle'));
    }
}
