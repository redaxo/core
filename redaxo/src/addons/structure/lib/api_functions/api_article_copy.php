<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_api_article_copy extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('copyArticle[]')) {
            throw new rex_api_exception('User has no permission to copy articles!');
        }

        $articleId = rex_request('article_id', 'int');
        $clang = rex_request('clang', 'int', 1);
        // The destination category in which the given article will be copied
        $categoryCopyIdNew = rex_request('category_copy_id_new', 'int');

        $article = rex_article::get($articleId);
        if (!$article instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '"!');
        }

        if (0 !== $categoryCopyIdNew && !rex_category::get($categoryCopyIdNew)) {
            throw new rex_api_exception('Unable to find category with id "' . $categoryCopyIdNew . '"!');
        }

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryCopyIdNew)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        $context = new rex_context([
            'page' => rex_be_controller::getCurrentPage(),
            'clang' => $clang,
        ]);

        if (false !== ($newId = rex_article_service::copyArticle($articleId, $categoryCopyIdNew))) {
            $result = new rex_api_result(true, rex_i18n::msg('content_articlecopied'));
            rex_response::sendRedirect($context->getUrl([
                'article_id' => $newId,
                'info' => $result->getMessage(),
            ], false));
        }

        return new rex_api_result(false, rex_i18n::msg('content_errorcopyarticle'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
