<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article_delete extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('deleteArticle[]')) {
            throw new rex_api_exception('User has no permission to delete articles!');
        }

        $articleId = rex_request('article_id', 'int');

        $article = rex_article::get($articleId);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '"!');
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        return new rex_api_result(true, rex_article_service::deleteArticle($articleId));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
