<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('category_add')]
class CategoryAdd extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('addCategory[]')) {
            throw new ApiFunctionException('User has no permission to add categories!');
        }

        $parentId = Request::request('parent-category-id', 'int');

        if (0 !== $parentId && null === Category::get($parentId)) {
            throw new ApiFunctionException('Unable to find category with id "' . $parentId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($parentId)) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = Request::post('category-position', 'int');
        $data['catname'] = Request::post('category-name', 'string');
        return new Result(true, CategoryHandler::addCategory($parentId, $data));
    }
}
