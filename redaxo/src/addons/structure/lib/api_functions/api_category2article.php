<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_category2Article extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        // article2category and category2article share the same permission: article2category
        if (!$user->hasPerm('article2category[]')) {
            throw new rex_api_exception('User has no permission to convert categories to articles!');
        }

        $articleId = rex_request('article_id', 'int');

        $article = rex_article::get($articleId);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        if (rex_article_service::category2article($articleId)) {
            return new rex_api_result(true, rex_i18n::msg('content_toarticle_ok'));
        }

        return new rex_api_result(false, rex_i18n::msg('content_toarticle_failed'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
