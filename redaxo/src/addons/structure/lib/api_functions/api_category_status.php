<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category_status extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('publishCategory[]')) {
            throw new rex_api_exception('User has no permission to publish categories!');
        }

        $categoryId = rex_request('category-id', 'int');
        $clang = rex_request('clang', 'int');
        $status = rex_request('cat_status', 'int', null);

        if (!rex_category::get($categoryId, $clang)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryId . '" and clang "' . $clang . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        rex_category_service::categoryStatus($categoryId, $clang, $status);

        return new rex_api_result(true, rex_i18n::msg('category_status_updated'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
