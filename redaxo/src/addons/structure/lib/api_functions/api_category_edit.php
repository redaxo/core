<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category_edit extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('editCategory[]')) {
            throw new rex_api_exception('User has no permission to edit categories!');
        }

        $catId = rex_request('category-id', 'int');
        $clangId = rex_request('clang', 'int');

        if (!rex_category::get($catId, $clangId)) {
            throw new rex_api_exception('Unable to find category with id "' . $catId . '" and clang "' . $clangId . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clangId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($catId)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = rex_post('category-position', 'int');
        $data['catname'] = rex_post('category-name', 'string');
        return new rex_api_result(true, rex_category_service::editCategory($catId, $clangId, $data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
