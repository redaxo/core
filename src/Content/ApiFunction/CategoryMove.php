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
#[AsApiFunction('category_move')]
final class CategoryMove extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('moveCategory[]')) {
            throw new ApiFunctionException('User has no permission to move categories!');
        }

        // The category to move (parameter name is `article_id` for historical reasons)
        $categoryId = Request::request('article_id', 'int');
        // The destination category in which the given category will be moved
        $categoryIdNew = Request::request('category_id_new', 'int');

        if (null === Category::get($categoryId)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryId . '"!');
        }

        if (0 !== $categoryIdNew && null === Category::get($categoryIdNew)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryIdNew . '"!');
        }

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryIdNew)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if ($categoryId !== $categoryIdNew && CategoryHandler::moveCategory($categoryId, $categoryIdNew)) {
            return new Result(true, I18n::msg('category_moved'));
        }

        return new Result(false, I18n::msg('content_error_movecategory'));
    }
}
