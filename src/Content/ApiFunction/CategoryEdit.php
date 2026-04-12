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
#[AsApiFunction('category_edit')]
class CategoryEdit extends ApiFunction
{
    public function execute()
    {
        if (!Core::requireUser()->hasPerm('editCategory[]')) {
            throw new ApiFunctionException('User has no permission to edit categories!');
        }

        $catId = Request::request('category-id', 'int');
        $clangId = Request::request('clang', 'int');

        $user = Core::requireUser();

        // check permissions
        if (!$user->getComplexPerm('structure')->hasCategoryPerm($catId)) {
            throw new ApiFunctionException('user has no permission for this category!');
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = Request::post('category-position', 'int');
        $data['catname'] = Request::post('category-name', 'string');
        return new Result(true, CategoryHandler::editCategory($catId, $clangId, $data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
