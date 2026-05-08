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
#[AsApiFunction('category_to_article')]
class CategoryToArticle extends ApiFunction
{
    public function execute(): Result
    {
        $articleId = Request::request('article_id', 'int');
        $categoryId = Article::get($articleId)->getCategoryId();
        $user = Core::requireUser();

        // Check permissions: ArticleToCategory and CategoryToArticle share the same permission: ArticleToCategory
        if ($user->hasPerm('article2category[]') && $user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            if (ArticleHandler::category2article($articleId)) {
                return new Result(true, I18n::msg('content_toarticle_ok'));
            }

            return new Result(false, I18n::msg('content_toarticle_failed'));
        }
        throw new ApiFunctionException('User has no permission for this article!');
    }
}
