<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
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
        $categoryId = Request::request('category-id', 'int');
        $clang = Request::request('clang', 'int');
        $status = Request::request('cat_status', 'int', null);
        $user = Core::requireUser();

        // Check permissions
        if ($user->getComplexPerm('structure')->hasCategoryPerm($categoryId) && $user->hasPerm('publishCategory[]')) {
            CategoryHandler::categoryStatus($categoryId, $clang, $status);
            return new Result(true, I18n::msg('category_status_updated'));
        }

        throw new ApiFunctionException('User has no permission for this category!');
    }
}
