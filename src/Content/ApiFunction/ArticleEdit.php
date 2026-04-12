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
#[AsApiFunction('article_edit')]
class ArticleEdit extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('editArticle[]')) {
            throw new ApiFunctionException('User has no permission to edit articles!');
        }

        $categoryId = Request::request('category_id', 'int');
        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');

        // check permissions
        if (!Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }

        // --------------------- ARTIKEL EDIT
        $data = [];
        $data['priority'] = Request::post('article-position', 'int');
        $data['name'] = Request::post('article-name', 'string');
        $data['template_id'] = Request::post('template_id', 'int');
        return new Result(true, ArticleHandler::editArticle($articleId, $clang, $data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
