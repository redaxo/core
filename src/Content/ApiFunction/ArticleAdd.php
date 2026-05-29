<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_add')]
class ArticleAdd extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('addArticle[]')) {
            throw new ApiFunctionException('User has no permission to add articles!');
        }

        $categoryId = Request::request('category_id', 'int');

        if (0 !== $categoryId && null === Category::get($categoryId)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        $data = [];
        $data['name'] = Request::post('article-name', 'string');
        $data['priority'] = Request::post('article-position', 'int');
        $data['template'] = Request::post('template', 'string');
        $data['category_id'] = $categoryId;
        return new Result(true, ArticleHandler::addArticle($data));
    }
}
