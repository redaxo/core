<?php

/**
 * @package redaxo\structure\content
 *
 * @internal
 */
class rex_api_content_slice_status extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('publishSlice[]')) {
            throw new rex_api_exception('User has no permission to publish slices!');
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

        $sliceId = rex_request('slice_id', 'int');
        $status = rex_request('status', 'int');

        // the slice must belong to the article whose category permission was checked above, otherwise slices of
        // categories the user has no permission for could be addressed
        $slice = rex_sql::factory();
        $slice->setQuery('SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE id = ? AND article_id = ? AND clang_id = ?', [$sliceId, $articleId, $clang]);
        if (1 !== $slice->getRows()) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        rex_content_service::sliceStatus($sliceId, $status);

        return new rex_api_result(true);
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
