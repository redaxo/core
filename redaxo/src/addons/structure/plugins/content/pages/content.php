<?php

/** Verwaltung der Inhalte. EditierModul / Metadaten ... */

$articleId = rex_request('article_id', 'int');
$clang = rex_request('clang', 'int');
$sliceId = rex_request('slice_id', 'int', '');

$articleId = rex_article::get($articleId) ? $articleId : 0;
$clang = rex_clang::exists($clang) ? $clang : rex_clang::getStartId();

$articleRevision = 0;
$sliceRevision = 0;
$templateAttributes = [];

$warning = '';
$globalWarning = '';
$info = '';
$globalInfo = '';

$article = rex_sql::factory();
$article->setQuery('
        SELECT
            article.*, template.attributes as template_attributes
        FROM
            ' . rex::getTablePrefix() . 'article as article
        LEFT JOIN ' . rex::getTablePrefix() . 'template as template
            ON template.id=article.template_id
        WHERE
            article.id=?
            AND clang_id=?', [$articleId, $clang]);

if (1 !== $article->getRows()) {
    echo rex_view::title(rex_i18n::msg('content'), '');
    echo rex_view::error(rex_i18n::msg('article_doesnt_exist'));
    return;
}

// ----- ctype holen
$templateAttributes = $article->getArrayValue('template_attributes');

// Für Artikel ohne Template
if (!is_array($templateAttributes)) {
    $templateAttributes = [];
}

$ctypes = $templateAttributes['ctype'] ?? []; // ctypes - aus dem template

$ctype = rex_request('ctype', 'int', 1);
if (!array_key_exists($ctype, $ctypes)) {
    $ctype = 1;
}

// ----- Artikel wurde gefunden - Kategorie holen
$OOArt = rex_article::get($articleId, $clang);
$categoryId = $OOArt->getCategoryId();

// ----- Request Parameter
$subpage = rex_be_controller::getCurrentPagePart(2);
$function = rex_request('function', 'string');
$warning = rex_escape(rex_request('warning', 'string'));
$info = rex_escape(rex_request('info', 'string'));

$context = new rex_context([
    'page' => rex_be_controller::getCurrentPage(),
    'article_id' => $articleId,
    'category_id' => $categoryId,
    'clang' => $clang,
    'ctype' => $ctype,
]);

// ----- Titel anzeigen
echo rex_view::title(rex_i18n::msg('content') . ': ' . rex_escape($OOArt->getName()), '');

// ----- Languages
echo rex_view::clangSwitchAsButtons($context);

// ----- category pfad und rechte
require rex_path::addon('structure', 'functions/function_rex_category.php');

// ----- EXTENSION POINT
echo rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_HEADER', '', [
    'article_id' => $articleId,
    'clang' => $clang,
    'function' => $function,
    'slice_id' => $sliceId,
    'page' => rex_be_controller::getCurrentPage(),
    'ctype' => $ctype,
    'category_id' => $categoryId,
    'article_revision' => &$articleRevision,
    'slice_revision' => &$sliceRevision,
]));

$user = rex::requireUser();

// ----------------- HAT USER DIE RECHTE AN DIESEM ARTICLE ODER NICHT
if (
    !$user->getComplexPerm('clang')->hasPerm($clang)
    || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
) {
    // ----- hat keine rechte an diesem artikel
    echo rex_view::warning(rex_i18n::msg('no_rights_to_edit'));
} else {
    // ----- hat rechte an diesem artikel

    // ------------------------------------------ Slice add/edit/delete
    if (rex_request('save', 'boolean') && in_array($function, ['add', 'edit', 'delete']) && !rex_csrf_token::factory('structure_content_slice')->isValid()) {
        $globalWarning = rex_i18n::msg('csrf_token_invalid');
        $function = '';
    }

    if (rex_request('save', 'boolean') && in_array($function, ['add', 'edit', 'delete'])) {
        // ----- check module

        $CM = rex_sql::factory();
        $moduleId = null;
        if ('edit' == $function || 'delete' == $function) {
            // edit/ delete
            // article_id must match: the permission check above is based on the requested article, so slices of
            // other articles (possibly in categories the user has no permission for) must not be addressable here
            $CM->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'article_slice LEFT JOIN ' . rex::getTablePrefix() . 'module ON ' . rex::getTablePrefix() . 'article_slice.module_id=' . rex::getTablePrefix() . 'module.id WHERE ' . rex::getTablePrefix() . 'article_slice.id=? AND article_id=? AND clang_id=?', [$sliceId, $articleId, $clang]);
            if (1 == $CM->getRows()) {
                $moduleId = $CM->getValue('' . rex::getTablePrefix() . 'article_slice.module_id');
            }
        } else {
            // add
            $moduleId = rex_post('module_id', 'int');
            $CM->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'module WHERE id=?', [$moduleId]);
        }

        if (1 != $CM->getRows()) {
            // ------------- MODUL IST NICHT VORHANDEN
            $globalWarning = rex_i18n::msg('module_not_found');
            $sliceId = 0;
            $function = '';
        } else {
            // ------------- MODUL IST VORHANDEN

            // ----- RECHTE AM MODUL ?
            if ('delete' != $function && !rex_template::hasModule($templateAttributes, $ctype, $moduleId)) {
                $globalWarning = rex_i18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } elseif (!$user->getComplexPerm('modules')->hasPerm($moduleId)) {
                // ----- RECHTE AM MODUL: NEIN
                $globalWarning = rex_i18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } else {
                // ----- RECHTE AM MODUL: JA

                // ***********************  daten einlesen

                $newsql = rex_sql::factory();
                // $newsql->setDebug();

                // ----- PRE SAVE ACTION [ADD/EDIT/DELETE]
                $action = new rex_article_action($moduleId, $function, $newsql);
                $action->setRequestValues();
                $action->exec(rex_article_action::PRESAVE);
                $actionMessage = implode('<br />', $action->getMessages());
                // ----- / PRE SAVE ACTION

                // Werte werden aus den REX_ACTIONS übernommen wenn SAVE=true
                if (!$action->getSave()) {
                    // ----- DONT SAVE/UPDATE SLICE
                    if ('' != $actionMessage) {
                        $warning = $actionMessage;
                    } elseif ('delete' == $function) {
                        $warning = rex_i18n::msg('slice_deleted_error');
                    } else {
                        $warning = rex_i18n::msg('slice_saved_error');
                    }
                } else {
                    if ($actionMessage) {
                        $actionMessage .= '<br />';
                    }

                    // clone sql object to preserve values in sql object given to rex_article_action
                    // otherwise the POSTSAVE action did not have access to values
                    $newsql = clone $newsql;

                    // ----- SAVE/UPDATE SLICE
                    if ('add' == $function || 'edit' == $function) {
                        $sliceTable = rex::getTablePrefix() . 'article_slice';
                        $newsql->setTable($sliceTable);

                        if ('edit' == $function) {
                            $newsql->setWhere(['id' => $sliceId]);
                        } else {
                            // determine priority value to get the new slice into the right order
                            $prevSlice = rex_sql::factory();
                            // $prevSlice->setDebug();
                            if (-1 == $sliceId) {
                                $prevSlice->setQuery('SELECT IFNULL(MAX(priority),0)+1 as priority FROM ' . $sliceTable . ' WHERE article_id=? AND clang_id=? AND ctype_id=? AND revision=?', [$articleId, $clang, $ctype, $sliceRevision]);
                            } else {
                                $prevSlice->setQuery('SELECT * FROM ' . $sliceTable . ' WHERE id=?', [$sliceId]);
                            }

                            $priority = $prevSlice->getValue('priority');

                            $newsql->setValue('article_id', $articleId);
                            $newsql->setValue('module_id', $moduleId);
                            $newsql->setValue('clang_id', $clang);
                            $newsql->setValue('ctype_id', $ctype);
                            $newsql->setValue('revision', $sliceRevision);
                            $newsql->setValue('priority', $priority);
                        }

                        if ('edit' == $function) {
                            $newsql->addGlobalUpdateFields();

                            rex_extension::registerPoint(new rex_extension_point('SLICE_UPDATE', '', [
                                'slice_id' => $sliceId,
                                'article_id' => $articleId,
                                'clang_id' => $clang,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->update();
                            $info = $actionMessage . rex_i18n::msg('block_updated');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => rex_be_controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = rex_extension::registerPoint(new rex_extension_point('SLICE_UPDATED', $info, $epParams));
                            /* deprecated */ $info = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SLICE_UPDATED', $info, $epParams));
                            $info = rex_extension::registerPoint(new rex_extension_point_art_content_updated($OOArt, 'slice_updated', $info));
                        } else {
                            $newsql->addGlobalUpdateFields();
                            $newsql->addGlobalCreateFields();

                            rex_extension::registerPoint(new rex_extension_point('SLICE_ADD', '', [
                                'article_id' => $articleId,
                                'clang_id' => $clang,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->insert();
                            $sliceId = (int) $newsql->getLastId();

                            rex_sql_util::organizePriorities(
                                rex::getTable('article_slice'),
                                'priority',
                                'article_id=' . $articleId . ' AND clang_id=' . $clang . ' AND ctype_id=' . $ctype . ' AND revision=' . (int) $sliceRevision,
                                'priority, updatedate DESC',
                            );

                            $info = $actionMessage . rex_i18n::msg('block_added');
                            $function = '';
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => rex_be_controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = rex_extension::registerPoint(new rex_extension_point('SLICE_ADDED', $info, $epParams));
                            /* deprecated */ $info = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SLICE_ADDED', $info, $epParams));
                            $info = rex_extension::registerPoint(new rex_extension_point_art_content_updated($OOArt, 'slice_added', $info));
                        }
                    } else {
                        // make delete

                        if (rex_content_service::deleteSlice($sliceId)) {
                            $globalInfo = rex_i18n::msg('block_deleted');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => rex_be_controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $globalInfo = rex_extension::registerPoint(new rex_extension_point('SLICE_DELETED', $globalInfo, $epParams));
                            /* deprecated */ $globalInfo = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SLICE_DELETED', $globalInfo, $epParams));
                            $globalInfo = rex_extension::registerPoint(new rex_extension_point_art_content_updated($OOArt, 'slice_deleted', $globalInfo));
                        } else {
                            $globalWarning = rex_i18n::msg('block_not_deleted');
                        }
                    }
                    // ----- / SAVE SLICE

                    // ----- artikel neu generieren
                    $EA = rex_sql::factory();
                    $EA->setTable(rex::getTablePrefix() . 'article');
                    $EA->setWhere(['id' => $articleId, 'clang_id' => $clang]);
                    $EA->addGlobalUpdateFields();
                    $EA->update();
                    rex_article_cache::delete($articleId, $clang);

                    rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_ARTICLE_UPDATED', '', [
                        'id' => $articleId,
                        'clang' => $clang,
                    ]));

                    // ----- POST SAVE ACTION [ADD/EDIT/DELETE]
                    $action->exec(rex_article_action::POSTSAVE);
                    if ($messages = $action->getMessages()) {
                        $info .= '<br />' . implode('<br />', $messages);
                    }
                    // ----- / POST SAVE ACTION

                    // Update Button wurde gedrückt?
                    if (rex_post('btn_save', 'string')) {
                        $function = '';
                    }
                }
            }
        }
    }
    // ------------------------------------------ END: Slice add/edit/delete

    // ------------------------------------------ START: CONTENT HEAD MENUE

    $editPage = rex_be_controller::getPageObject('content/edit');

    foreach ($ctypes as $key => $val) {
        $key = (int) $key;
        $hasSlice = true;
        if ($ctype != $key) {
            $hasSlice = null !== rex_article_slice::getFirstSliceForCtype($key, $articleId, $clang);
        }
        $editPage->addSubpage((new rex_be_page('ctype' . $key, rex_i18n::translate($val)))
            ->setHref(['page' => 'content/edit', 'article_id' => $articleId, 'clang' => $clang, 'ctype' => $key])
            ->setIsActive($ctype == $key)
            ->setItemAttr('class', $hasSlice ? '' : 'rex-empty'),
        );
    }

    $leftNav = rex_be_navigation::factory();
    $rightNav = rex_be_navigation::factory();

    foreach (rex_be_controller::getPageObject('content')->getSubpages() as $subpage) {
        if (!$subpage->hasHref()) {
            $subpage->setHref($context->getUrl(['page' => $subpage->getFullKey()], false));
        }
        // If the user has none of the content function permissions the page 'functions' will not be displayed
        if (
            'functions' != $subpage->getKey()
            || $user->hasPerm('article2category[]')
            || $user->hasPerm('article2startarticle[]')
            || $user->hasPerm('copyArticle[]')
            || $user->hasPerm('moveArticle[]')
            || $user->hasPerm('moveCategory[]')
            || ($user->hasPerm('copyContent[]') && $user->getComplexPerm('clang')->count() > 1)
        ) {
            if ($subpage->getItemAttr('left')) {
                $leftNav->addPage($subpage);
            } else {
                $rightNav->addPage($subpage);
            }
        }
        $subpage->removeItemAttr('left');
    }

    $blocks = $leftNav->getNavigation();
    $navigation = current($blocks);
    $contentNaviLeft = $navigation['navigation'];

    $blocks = $rightNav->getNavigation();
    $navigation = current($blocks);
    $contentNaviRight = $navigation['navigation'] ?? [];

    $contentNaviRight[] = ['title' => '<a href="' . rex_getUrl($articleId, $clang) . '" onclick="window.open(this.href); return false;">' . rex_i18n::msg('article_show') . ' <i class="rex-icon rex-icon-external-link"></i></a>'];

    $fragment = new rex_fragment();
    $fragment->setVar('id', 'rex-js-structure-content-nav', false);
    $fragment->setVar('left', $contentNaviLeft, false);
    $fragment->setVar('right', $contentNaviRight, false);

    $contentMain = $fragment->parse('core/navigations/content.php');

    // ------------------------------------------ END: CONTENT HEAD MENUE

    // ------------------------------------------ WARNING
    if ('' != $globalWarning) {
        $contentMain .= rex_view::warning($globalWarning);
    }
    if ('' != $globalInfo) {
        $contentMain .= rex_view::success($globalInfo);
    }

    // --------------------------------------------- API MESSAGES
    $contentMain .= rex_api_function::getMessage();

    if ('' != $warning) {
        $contentMain .= rex_view::warning($warning);
    }
    if ('' != $info) {
        $contentMain .= rex_view::success($info);
    }

    // ----- EXTENSION POINT
    $contentMain .= rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_BEFORE_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => rex_be_controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    // ------------------------------------------ START: MODULE EDITIEREN/ADDEN ETC.
    $contentMain .= rex_be_controller::includeCurrentPageSubPath(compact('info', 'warning', 'templateAttributes', 'article', 'articleId', 'categoryId', 'clang', 'sliceId', 'sliceRevision', 'function', 'ctype', 'context'));
    // ------------------------------------------ END: AUSGABE

    // ----- EXTENSION POINT
    $contentMain .= rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_AFTER_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => rex_be_controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    $contentMain = '<section id="rex-js-page-main-content" data-pjax-container="#rex-js-page-main-content">' . $contentMain . '</section>';

    // ----- EXTENSION POINT
    $contentSidebar = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SIDEBAR', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => rex_be_controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    $fragment = new rex_fragment();
    $fragment->setVar('content', $contentMain, false);
    $fragment->setVar('sidebar', $contentSidebar, false);

    echo $fragment->parse('core/page/main_content.php');
}
