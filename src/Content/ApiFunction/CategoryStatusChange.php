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
#[AsApiFunction('category_status_change')]
class CategoryStatusChange extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('publishCategory[]')) {
            throw new ApiFunctionException('User has no permission to publish categories!');
        }

        $categoryId = Request::request('category-id', 'int');
        $clang = Request::request('clang', 'int');
        $status = Request::request('cat_status', 'int', null);

        if (null === Category::get($categoryId, $clang)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryId . '" and clang "' . $clang . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        CategoryHandler::categoryStatus($categoryId, $clang, $status);

        return new Result(true, I18n::msg('category_status_updated'));
    }
}
