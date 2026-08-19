<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category_move extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('moveCategory[]')) {
            throw new rex_api_exception('User has no permission to move categories!');
        }

        // The category to move (parameter name is `article_id` for historical reasons)
        $categoryId = rex_request('article_id', 'int');
        // The destination category in which the given category will be moved
        $categoryIdNew = rex_request('category_id_new', 'int');

        if (!rex_category::get($categoryId)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryId . '"!');
        }

        if (0 !== $categoryIdNew && !rex_category::get($categoryIdNew)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryIdNew . '"!');
        }

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryIdNew)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        if ($categoryId !== $categoryIdNew && rex_category_service::moveCategory($categoryId, $categoryIdNew)) {
            return new rex_api_result(true, rex_i18n::msg('category_moved'));
        }

        return new rex_api_result(false, rex_i18n::msg('content_error_movecategory'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
