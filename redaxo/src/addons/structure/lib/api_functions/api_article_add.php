<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article_add extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('addArticle[]')) {
            throw new rex_api_exception('User has no permission to add articles!');
        }

        $categoryId = rex_request('category_id', 'int');

        if (0 !== $categoryId && !rex_category::get($categoryId)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        $data = [];
        $data['name'] = rex_post('article-name', 'string');
        $data['priority'] = rex_post('article-position', 'int');
        $data['template_id'] = rex_post('template_id', 'int');
        $data['category_id'] = $categoryId;
        return new rex_api_result(true, rex_article_service::addArticle($data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
