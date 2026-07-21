<?php

/**
 * @package redaxo\structure\content
 */
class rex_api_content_copy extends rex_api_function
{
    /**
     * @throws rex_api_exception
     *
     * @return rex_api_result
     */
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('copyContent[]')) {
            throw new rex_api_exception('User has no permission to copy content!');
        }

        $articleId = rex_request('article_id', 'int');
        $clangA = rex_request('clang_a', 'int');
        $clangB = rex_request('clang_b', 'int');
        $overwrite = rex_request('overwrite', 'bool', false);

        $article = rex_article::get($articleId, $clangA);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '" and clang "' . $clangA . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clangA)
            || !$user->getComplexPerm('clang')->hasPerm($clangB)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        if (rex_content_service::copyContent($articleId, $articleId, $clangA, $clangB, null, $overwrite)) {
            return new rex_api_result(true, rex_i18n::msg('content_contentcopy'));
        }

        return new rex_api_result(false, rex_i18n::msg('content_errorcopy'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
