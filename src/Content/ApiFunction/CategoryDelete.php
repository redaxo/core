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
#[AsApiFunction('category_delete')]
class CategoryDelete extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('deleteCategory[]')) {
            throw new ApiFunctionException('User has no permission to delete categories!');
        }

        $catId = Request::request('category-id', 'int');

        if (null === Category::get($catId)) {
            throw new ApiFunctionException('Unable to find category with id "' . $catId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($catId)) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        return new Result(true, CategoryHandler::deleteCategory($catId));
    }
}
