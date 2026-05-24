<?php

/**
 * @package redaxo\structure\content
 *
 * @internal
 */
class rex_api_content_move_slice extends rex_api_function
{
    public function execute()
    {
        $user = rex::requireUser();
        if (!$user->hasPerm('moveSlice[]')) {
            throw new rex_api_exception('User has no permission to move slices!');
        }

        $articleId = rex_request('article_id', 'int');
        $clang = rex_request('clang', 'int');
        $sliceId = rex_request('slice_id', 'int');
        $direction = rex_request('direction', 'string');

        $ooArt = rex_article::get($articleId, $clang);
        if (!$ooArt instanceof rex_article) {
            throw new rex_api_exception('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        $CM = rex_sql::factory();
        $CM->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice left join ' . rex::getTablePrefix() . 'module on ' . rex::getTablePrefix() . 'article_slice.module_id=' . rex::getTablePrefix() . 'module.id where ' . rex::getTablePrefix() . 'article_slice.id=? and clang_id=?', [$sliceId, $clang]);
        if (1 != $CM->getRows()) {
            throw new rex_api_exception(rex_i18n::msg('module_not_found'));
        }
        $moduleId = (int) $CM->getValue(rex::getTablePrefix() . 'article_slice.module_id');

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($ooArt->getCategoryId())
            || !$user->getComplexPerm('modules')->hasPerm($moduleId)
        ) {
            throw new rex_api_exception(rex_i18n::msg('no_rights_to_this_function'));
        }

        $message = rex_content_service::moveSlice($sliceId, $clang, $direction);

        return new rex_api_result(true, $message);
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
