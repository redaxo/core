<?php

/**
 * @package redaxo\structure
 */
class rex_api_article_move extends rex_api_function
{
    /**
     * @throws rex_api_exception
     *
     * @return rex_api_result
     */
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('moveArticle[]')) {
            throw new rex_api_exception('User has no permission to move articles!');
        }

        // The article to move
        $articleId = rex_request('article_id', 'int');
        // The destination category in which the given category will be moved
        $categoryIdNew = rex_request('category_id_new', 'int');

        $article = rex_article::get($articleId);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '"!');
        }

        if (0 !== $categoryIdNew && !rex_category::get($categoryIdNew)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryIdNew . '"!');
        }

        $categoryId = $article->getCategoryId();

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryIdNew)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        if (rex_article_service::moveArticle($articleId, $categoryId, $categoryIdNew)) {
            return new rex_api_result(true, rex_i18n::msg('content_articlemoved'));
        }

        return new rex_api_result(false, rex_i18n::msg('content_errormovearticle'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
