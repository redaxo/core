<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category_delete extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('deleteCategory[]')) {
            throw new rex_api_exception('User has no permission to delete categories!');
        }

        $catId = rex_request('category-id', 'int');

        if (!rex_category::get($catId)) {
            throw new rex_api_exception('Unable to find category with id "' . $catId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($catId)) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        return new rex_api_result(true, rex_category_service::deleteCategory($catId));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
