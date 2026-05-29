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
#[AsApiFunction('category_edit')]
class CategoryEdit extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('editCategory[]')) {
            throw new ApiFunctionException('User has no permission to edit categories!');
        }

        $catId = Request::request('category-id', 'int');
        $clangId = Request::request('clang', 'int');

        if (null === Category::get($catId, $clangId)) {
            throw new ApiFunctionException('Unable to find category with id "' . $catId . '" and clang "' . $clangId . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clangId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($catId)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = Request::post('category-position', 'int');
        $data['catname'] = Request::post('category-name', 'string');
        return new Result(true, CategoryHandler::editCategory($catId, $clangId, $data));
    }
}
