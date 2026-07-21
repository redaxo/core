<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article_edit extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('editArticle[]')) {
            throw new rex_api_exception('User has no permission to edit articles!');
        }

        $articleId = rex_request('article_id', 'int');
        $clang = rex_request('clang', 'int');

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

        $data = [];
        $data['priority'] = rex_post('article-position', 'int');
        $data['name'] = rex_post('article-name', 'string');
        $data['template_id'] = rex_post('template_id', 'int');
        return new rex_api_result(true, rex_article_service::editArticle($articleId, $clang, $data));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
