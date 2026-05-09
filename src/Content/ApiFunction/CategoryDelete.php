<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
#[AsApiFunction('category_delete')]
class CategoryDelete extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('deleteCategory[]')) {
            throw new ApiFunctionException('User has no permission to delete categories!');
        }

        $catId = Request::request('category-id', 'int');

        // check permissions
        if (!Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($catId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }

        return new Result(true, CategoryHandler::deleteCategory($catId));
    }
}
