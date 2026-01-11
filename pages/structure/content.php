<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleAction;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$articleId = Request::request('article_id', 'int');
$clang = Request::request('clang', 'int');
$sliceId = Request::request('slice_id', 'int', '');

$articleId = Article::get($articleId) ? $articleId : 0;
$clang = Language::exists($clang) ? $clang : Language::getStartId();

$articleRevision = 0;
$sliceRevision = 0;
$templateAttributes = [];

$warning = '';
$globalWarning = '';
$info = '';
$globalInfo = '';

$article = Sql::factory();
$article->setQuery('
        SELECT
            article.*, template.attributes as template_attributes
        FROM
            ' . Core::getTablePrefix() . 'article as article
        LEFT JOIN ' . Core::getTablePrefix() . 'template as template
            ON template.id=article.template_id
        WHERE
            article.id=?
            AND clang_id=?', [$articleId, $clang]);

if (1 !== $article->getRows()) {
    echo View::title(I18n::msg('content'), '');
    echo Message::error(I18n::msg('article_doesnt_exist'));
    return;
}

// ----- ctype holen
$templateAttributes = $article->getArrayValue('template_attributes');

$ctypes = $templateAttributes['ctype'] ?? []; // ctypes - aus dem template

$ctype = Request::request('ctype', 'int', 1);
if (!array_key_exists($ctype, $ctypes)) {
    $ctype = 1;
}

// ----- Artikel wurde gefunden - Kategorie holen
$OOArt = Article::get($articleId, $clang);
$categoryId = $OOArt->getCategoryId();

// ----- Request Parameter
$subpage = Controller::getCurrentPagePart(2);
$function = Request::request('function', 'string');
$warning = escape(Request::request('warning', 'string'));
$info = escape(Request::request('info', 'string'));

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'article_id' => $articleId,
    'category_id' => $categoryId,
    'clang' => $clang,
    'ctype' => $ctype,
]);

// ----- Titel anzeigen
echo View::title(I18n::msg('content') . ': ' . escape($OOArt->getName()), '');

// ----- Languages
echo View::clangSwitchAsButtons($context);

// ----- category pfad und rechte
echo View::structureBreadcrumb($categoryId, $articleId, $clang);

// ----- EXTENSION POINT
echo Extension::registerPoint(new ExtensionPoint('STRUCTURE_CONTENT_HEADER', '', [
    'article_id' => $articleId,
    'clang' => $clang,
    'function' => $function,
    'slice_id' => $sliceId,
    'page' => Controller::getCurrentPage(),
    'ctype' => $ctype,
    'category_id' => $categoryId,
    'article_revision' => &$articleRevision,
    'slice_revision' => &$sliceRevision,
]));

$user = Core::requireUser();

// ----------------- HAT USER DIE RECHTE AN DIESEM ARTICLE ODER NICHT
if (!$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
    // ----- hat keine rechte an diesem artikel
    echo Message::warning(I18n::msg('no_rights_to_edit'));
} else {
    // ----- hat rechte an diesem artikel

    // ------------------------------------------ Slice add/edit/delete
    if (Request::request('save', 'boolean') && in_array($function, ['add', 'edit', 'delete'])) {
        // ----- check module

        $CM = Sql::factory();
        $moduleId = null;
        if ('edit' == $function || 'delete' == $function) {
            // edit/ delete
            $CM->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article_slice LEFT JOIN ' . Core::getTablePrefix() . 'module ON ' . Core::getTablePrefix() . 'article_slice.module_id=' . Core::getTablePrefix() . 'module.id WHERE ' . Core::getTablePrefix() . 'article_slice.id=? AND clang_id=?', [$sliceId, $clang]);
            if (1 == $CM->getRows()) {
                $moduleId = $CM->getValue('' . Core::getTablePrefix() . 'article_slice.module_id');
            }
        } else {
            // add
            $moduleId = Request::post('module_id', 'int');
            $CM->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'module WHERE id=?', [$moduleId]);
        }

        if (1 != $CM->getRows()) {
            // ------------- MODUL IST NICHT VORHANDEN
            $globalWarning = I18n::msg('module_not_found');
            $sliceId = 0;
            $function = '';
        } else {
            // ------------- MODUL IST VORHANDEN

            // ----- RECHTE AM MODUL ?
            if ('delete' != $function && !Template::hasModule($templateAttributes, $ctype, $moduleId)) {
                $globalWarning = I18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } elseif (!$user->getComplexPerm('modules')->hasPerm($moduleId)) {
                // ----- RECHTE AM MODUL: NEIN
                $globalWarning = I18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } else {
                // ----- RECHTE AM MODUL: JA

                // ***********************  daten einlesen

                $newsql = Sql::factory();
                // $newsql->setDebug();

                // ----- PRE SAVE ACTION [ADD/EDIT/DELETE]
                $action = new ArticleAction($moduleId, $function, $newsql);
                $action->setRequestValues();
                $action->exec(ArticleAction::PRESAVE);
                $actionMessage = implode('<br />', $action->getMessages());
                // ----- / PRE SAVE ACTION

                // Werte werden aus den REX_ACTIONS übernommen wenn SAVE=true
                if (!$action->getSave()) {
                    // ----- DONT SAVE/UPDATE SLICE
                    if ('' != $actionMessage) {
                        $warning = $actionMessage;
                    } elseif ('delete' == $function) {
                        $warning = I18n::msg('slice_deleted_error');
                    } else {
                        $warning = I18n::msg('slice_saved_error');
                    }
                } else {
                    if ($actionMessage) {
                        $actionMessage .= '<br />';
                    }

                    // clone sql object to preserve values in sql object given to ArticleAction
                    // otherwise the POSTSAVE action did not have access to values
                    $newsql = clone $newsql;

                    // ----- SAVE/UPDATE SLICE
                    if ('add' == $function || 'edit' == $function) {
                        $sliceTable = Core::getTablePrefix() . 'article_slice';
                        $newsql->setTable($sliceTable);

                        if ('edit' == $function) {
                            $newsql->setWhere(['id' => $sliceId]);
                        } else {
                            // determine priority value to get the new slice into the right order
                            $prevSlice = Sql::factory();
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

                            Extension::registerPoint(new ExtensionPoint('SLICE_UPDATE', '', [
                                'slice_id' => $sliceId,
                                'article_id' => $articleId,
                                'clang_id' => $clang,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->update();
                            $info = $actionMessage . I18n::msg('block_updated');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = Extension::registerPoint(new ExtensionPoint('SLICE_UPDATED', $info, $epParams));
                            $info = Extension::registerPoint(new ArticleContentUpdated($OOArt, 'slice_updated', $info));
                        } else {
                            $newsql->addGlobalUpdateFields();
                            $newsql->addGlobalCreateFields();

                            Extension::registerPoint(new ExtensionPoint('SLICE_ADD', '', [
                                'article_id' => $articleId,
                                'clang_id' => $clang,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->insert();
                            $sliceId = $newsql->getLastId();

                            Util::organizePriorities(
                                Core::getTable('article_slice'),
                                'priority',
                                'article_id=' . $articleId . ' AND clang_id=' . $clang . ' AND ctype_id=' . $ctype . ' AND revision=' . (int) $sliceRevision,
                                'priority, updatedate DESC',
                            );

                            $info = $actionMessage . I18n::msg('block_added');
                            $function = '';
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = Extension::registerPoint(new ExtensionPoint('SLICE_ADDED', $info, $epParams));
                            $info = Extension::registerPoint(new ArticleContentUpdated($OOArt, 'slice_added', $info));
                        }
                    } else {
                        // make delete

                        if (ContentHandler::deleteSlice($sliceId)) {
                            $globalInfo = I18n::msg('block_deleted');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $clang,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_id' => $moduleId,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $globalInfo = Extension::registerPoint(new ExtensionPoint('SLICE_DELETED', $globalInfo, $epParams));
                            $globalInfo = Extension::registerPoint(new ArticleContentUpdated($OOArt, 'slice_deleted', $globalInfo));
                        } else {
                            $globalWarning = I18n::msg('block_not_deleted');
                        }
                    }
                    // ----- / SAVE SLICE

                    // ----- artikel neu generieren
                    $EA = Sql::factory();
                    $EA->setTable(Core::getTablePrefix() . 'article');
                    $EA->setWhere(['id' => $articleId, 'clang_id' => $clang]);
                    $EA->addGlobalUpdateFields();
                    $EA->update();
                    ArticleCache::delete($articleId, $clang);

                    Extension::registerPoint(new ExtensionPoint('STRUCTURE_CONTENT_ARTICLE_UPDATED', '', [
                        'id' => $articleId,
                        'clang' => $clang,
                    ]));

                    // ----- POST SAVE ACTION [ADD/EDIT/DELETE]
                    $action->exec(ArticleAction::POSTSAVE);
                    if ($messages = $action->getMessages()) {
                        $info .= '<br />' . implode('<br />', $messages);
                    }
                    // ----- / POST SAVE ACTION

                    // Update Button wurde gedrückt?
                    if (Request::post('btn_save', 'string')) {
                        $function = '';
                    }
                }
            }
        }
    }
    // ------------------------------------------ END: Slice add/edit/delete

    // ------------------------------------------ START: CONTENT HEAD MENUE

    $editPage = Controller::getPageObject('content/edit');

    foreach ($ctypes as $key => $val) {
        $key = (int) $key;
        $hasSlice = true;
        if ($ctype != $key) {
            $hasSlice = null !== ArticleSlice::getFirstSliceForCtype($key, $articleId, $clang);
        }
        $editPage->addSubpage(new Page('ctype' . $key, I18n::translate($val))
            ->setHref(['page' => 'content/edit', 'article_id' => $articleId, 'clang' => $clang, 'ctype' => $key])
            ->setIsActive($ctype == $key)
            ->setItemAttr('class', $hasSlice ? '' : 'rex-empty'),
        );
    }

    $leftNav = Navigation::factory();
    $rightNav = Navigation::factory();

    foreach (Controller::getPageObject('content')->getSubpages() as $subpage) {
        if (!$subpage->hasHref()) {
            $subpage->setHref($context->getUrl(['page' => $subpage->getFullKey()]));
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

    $contentNaviRight[] = ['title' => '<a href="' . Url::article($articleId, $clang) . '" onclick="window.open(this.href); return false;">' . I18n::msg('article_show') . ' <i class="rex-icon rex-icon-external-link"></i></a>'];

    $fragment = new Fragment();
    $fragment->setVar('id', 'rex-js-structure-content-nav', false);
    $fragment->setVar('left', $contentNaviLeft, false);
    $fragment->setVar('right', $contentNaviRight, false);

    $contentMain = $fragment->parse('core/navigations/content.php');

    // ------------------------------------------ END: CONTENT HEAD MENUE

    // ------------------------------------------ WARNING
    if ('' != $globalWarning) {
        $contentMain .= Message::warning($globalWarning);
    }
    if ('' != $globalInfo) {
        $contentMain .= Message::success($globalInfo);
    }

    // --------------------------------------------- API MESSAGES
    $contentMain .= ApiFunction::getMessage();

    if ('' != $warning) {
        $contentMain .= Message::warning($warning);
    }
    if ('' != $info) {
        $contentMain .= Message::success($info);
    }

    // ----- EXTENSION POINT
    $contentMain .= Extension::registerPoint(new ExtensionPoint('STRUCTURE_CONTENT_BEFORE_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => Controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    // ------------------------------------------ START: MODULE EDITIEREN/ADDEN ETC.
    $contentMain .= Controller::includeCurrentPageSubPath(compact('info', 'warning', 'templateAttributes', 'article', 'articleId', 'categoryId', 'clang', 'sliceId', 'sliceRevision', 'function', 'ctype', 'context'));
    // ------------------------------------------ END: AUSGABE

    // ----- EXTENSION POINT
    $contentMain .= Extension::registerPoint(new ExtensionPoint('STRUCTURE_CONTENT_AFTER_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => Controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    $contentMain = '<section id="rex-js-page-main-content" data-pjax-container="#rex-js-page-main-content">' . $contentMain . '</section>';

    // ----- EXTENSION POINT
    $contentSidebar = Extension::registerPoint(new ExtensionPoint('STRUCTURE_CONTENT_SIDEBAR', '', [
        'article_id' => $articleId,
        'clang' => $clang,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => Controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    $fragment = new Fragment();
    $fragment->setVar('content', $contentMain, false);
    $fragment->setVar('sidebar', $contentSidebar, false);

    echo $fragment->parse('core/page/main_content.php');
}
