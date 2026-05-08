<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('category_move')]
class CategoryMove extends ApiFunction
{
    public function execute(): Result
    {
        // The category to move
        $articleId = Request::request('article_id', 'int');
        $categoryId = Article::get($articleId)->getCategoryId();
        // The destination category in which the given category will be moved
        $categoryIdNew = Request::request('category_id_new', 'int');

        $user = Core::requireUser();

        // Check permissions
        if (
            $user->hasPerm('moveCategory[]')
            && $user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
            && $user->getComplexPerm('structure')->hasCategoryPerm($categoryIdNew)
        ) {
            if ($categoryId != $categoryIdNew && CategoryHandler::moveCategory($categoryId, $categoryIdNew)) {
                return new Result(true, I18n::msg('category_moved'));
            }

            return new Result(false, I18n::msg('content_error_movecategory'));
        }

        throw new ApiFunctionException('user has no permission for this category!');
    }
}
