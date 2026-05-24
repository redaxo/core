<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article2category extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('article2category[]')) {
            throw new rex_api_exception('User has no permission to convert articles to categories!');
        }

        $articleId = rex_request('article_id', 'int');

        $article = rex_article::get($articleId);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        if (rex_article_service::article2category($articleId)) {
            return new rex_api_result(true, rex_i18n::msg('content_tocategory_ok'));
        }

        return new rex_api_result(false, rex_i18n::msg('content_tocategory_failed'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
