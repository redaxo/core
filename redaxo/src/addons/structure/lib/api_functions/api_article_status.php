<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article_status extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('publishArticle[]')) {
            throw new rex_api_exception('User has no permission to publish articles!');
        }

        $articleId = rex_request('article_id', 'int');
        $clang = rex_request('clang', 'int');
        $status = rex_request('art_status', 'int', null);

        $article = rex_article::get($articleId, $clang);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        rex_article_service::articleStatus($articleId, $clang, $status);

        return new rex_api_result(true, rex_i18n::msg('article_status_updated'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
