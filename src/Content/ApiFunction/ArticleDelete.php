<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
#[AsApiFunction('article_delete')]
class ArticleDelete extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('deleteArticle[]')) {
            throw new ApiFunctionException('User has no permission to delete articles!');
        }

        $categoryId = Request::request('category_id', 'int');
        $articleId = Request::request('article_id', 'int');

        // Check permissions
        if (!Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }
        return new Result(true, ArticleHandler::deleteArticle($articleId));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
