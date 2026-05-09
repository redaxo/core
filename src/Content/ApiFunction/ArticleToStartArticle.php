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
#[AsApiFunction('article_to_startarticle')]
class ArticleToStartArticle extends ApiFunction
{
    public function execute()
    {
        $articleId = Request::request('article_id', 'int');
        $categoryId = Article::get($articleId)->getCategoryId();
        $user = Core::requireUser();

        // Check permissions
        if ($user->hasPerm('article2startarticle[]') && $user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            if (ArticleHandler::article2startarticle($articleId)) {
                return new Result(true, I18n::msg('content_tostartarticle_ok'));
            }

            return new Result(false, I18n::msg('content_tostartarticle_failed'));
        }

        throw new ApiFunctionException('user has no permission for this article!');
    }
}
