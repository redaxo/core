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
        $user = Core::requireUser();
        // article2category and category2article share the same permission: article2category
        if (!$user->hasPerm('article2category[]')) {
            throw new ApiFunctionException('User has no permission to convert categories to articles!');
        }

        $articleId = Request::request('article_id', 'int');

        $article = Article::get($articleId);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if (ArticleHandler::category2article($articleId)) {
            return new Result(true, I18n::msg('content_toarticle_ok'));
        }

        return new Result(false, I18n::msg('content_toarticle_failed'));
    }
}
