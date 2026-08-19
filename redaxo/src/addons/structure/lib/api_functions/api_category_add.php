<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category_add extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('addCategory[]')) {
            throw new rex_api_exception('User has no permission to add categories!');
        }

        $parentId = rex_request('parent-category-id', 'int');

        if (0 !== $parentId && !rex_category::get($parentId)) {
            throw new rex_api_exception('Unable to find category with id "' . $parentId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($parentId)) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        // prepare and validate parameters
        $data = [];
        $data['catpriority'] = rex_post('category-position', 'int');
        $data['catname'] = rex_post('category-name', 'string');
        return new rex_api_result(true, rex_category_service::addCategory($parentId, $data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
