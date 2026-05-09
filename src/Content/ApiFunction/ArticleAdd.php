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
#[AsApiFunction('article_add')]
class ArticleAdd extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('addArticle[]')) {
            throw new ApiFunctionException('User has no permission to add articles!');
        }

        $categoryId = Request::request('category_id', 'int');

        // check permissions
        if (!Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }

        $data = [];
        $data['name'] = Request::post('article-name', 'string');
        $data['priority'] = Request::post('article-position', 'int');
        $data['template_id'] = Request::post('template_id', 'int');
        $data['category_id'] = $categoryId;
        return new Result(true, ArticleHandler::addArticle($data));
    }
}
