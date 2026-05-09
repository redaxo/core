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
#[AsApiFunction('category_add')]
class CategoryAdd extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('addCategory[]')) {
            throw new ApiFunctionException('User has no permission to add categories!');
        }

        $parentId = Request::request('parent-category-id', 'int');

        // check permissions
        if (!Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($parentId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = Request::post('category-position', 'int');
        $data['catname'] = Request::post('category-name', 'string');
        return new Result(true, CategoryHandler::addCategory($parentId, $data));
    }
}
