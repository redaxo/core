<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceAction;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\Module;
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
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$articleId = Request::request('article_id', 'int');
$languageId = Request::request('clang', 'int');
$sliceId = Request::request('slice_id', 'int', '');

$articleId = Article::get($articleId) ? $articleId : 0;
$languageId = Language::exists($languageId) ? $languageId : Language::getStartId();

$articleRevision = 0;
$sliceRevision = 0;

$warning = '';
$globalWarning = '';
$info = '';
$globalInfo = '';

$article = Sql::factory();
$article->setQuery('
        SELECT article.*
        FROM ' . Core::getTablePrefix() . 'article as article
        WHERE
            article.id=?
            AND language_id=?', [$articleId, $languageId]);

if (1 !== $article->getRows()) {
    echo View::title(I18n::msg('content'), '');
    echo Message::error(I18n::msg('article_doesnt_exist'));
    return;
}

$templateKey = (string) $article->getValue('template');
$template = Template::get($templateKey);
$contentSections = $template?->getContentSections() ?? [];

$ctype = Request::request('ctype', 'int', 1);
if ($ctype < 2 || !$template?->hasContentSection($ctype)) {
    $ctype = 1;
}

// ----- Artikel wurde gefunden - Kategorie holen
$OOArt = Article::require($articleId, $languageId);
// Top level articles have no category, default to 0 (root) — backend pages expect an int category id.
$categoryId = $OOArt->categoryId ?? 0;

// ----- Request Parameter
$subpage = Controller::getCurrentPagePart(2);
$function = Request::request('function', 'string');
$warning = escape(Request::request('warning', 'string'));
$info = escape(Request::request('info', 'string'));

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'article_id' => $articleId,
    'category_id' => $categoryId,
    'clang' => $languageId,
    'ctype' => $ctype,
]);

// ----- Titel anzeigen
echo View::title(I18n::msg('content') . ': ' . escape($OOArt->name), '');

// ----- Languages
echo View::languageSwitchAsButtons($context);

// ----- category pfad und rechte
echo View::structureBreadcrumb($categoryId, $articleId, $languageId);

// ----- EXTENSION POINT
echo Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_HEADER', '', [
    'article_id' => $articleId,
    'clang' => $languageId,
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
if (
    !$user->getComplexPerm('clang')->hasPerm($languageId)
    || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)
) {
    // ----- hat keine rechte an diesem artikel
    echo Message::warning(I18n::msg('no_rights_to_edit'));
} else {
    // ----- hat rechte an diesem artikel

    // ------------------------------------------ Slice add/edit/delete
    if (Request::request('save', 'boolean') && in_array($function, ['add', 'edit', 'delete']) && !CsrfToken::factory('structure_content_slice')->isValid()) {
        $globalWarning = I18n::msg('csrf_token_invalid');
        $function = '';
    }

    if (Request::request('save', 'boolean') && in_array($function, ['add', 'edit', 'delete'])) {
        // ----- check module

        $CM = Sql::factory();
        $moduleKey = null;
        if ('edit' == $function || 'delete' == $function) {
            // edit/ delete
            // article_id must match: the permission check above is based on the requested article, so slices of
            // other articles (possibly in categories the user has no permission for) must not be addressable here
            $CM->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article_slice WHERE id=? AND article_id=? AND language_id=?', [$sliceId, $articleId, $languageId]);
            if (1 == $CM->getRows()) {
                $moduleKey = (string) $CM->getValue('module');
            }
        } else {
            // add
            $moduleKey = Request::post('module', 'string');
        }

        $module = $moduleKey ? Module::get($moduleKey) : null;

        if (null === $module || null === $moduleKey) {
            // ------------- MODUL IST NICHT VORHANDEN
            $globalWarning = I18n::msg('module_not_found');
            $sliceId = 0;
            $function = '';
        } else {
            // ------------- MODUL IST VORHANDEN

            // ----- RECHTE AM MODUL ?
            $category = $categoryId > 0 ? Category::get($categoryId) : null;
            if ('delete' != $function && (!Template::checkModuleAllowed($templateKey, $ctype, $moduleKey) || !$module->isAllowedInCategory($category))) {
                $globalWarning = I18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } elseif (!$user->getComplexPerm('modules')->hasPerm($moduleKey)) {
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
                $mode = match ($function) {
                    'edit' => ArticleSliceAction::EDIT,
                    'delete' => ArticleSliceAction::DELETE,
                    default => ArticleSliceAction::ADD,
                };
                $action = new ArticleSliceAction($mode, $articleId, $languageId, $ctype, $sliceId, $newsql);

                $action->setRequestValues();

                $module->onPresave($action);
                $actionMessage = implode('<br />', $action->messages);
                // ----- / PRE SAVE ACTION

                // Werte werden aus den REX_ACTIONS übernommen wenn SAVE=true
                if (!$action->save) {
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

                    // clone sql object to preserve values in sql object given to ArticleSliceAction
                    // otherwise the postsave hook did not have access to values
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
                                $prevSlice->setQuery('SELECT IFNULL(MAX(priority),0)+1 as priority FROM ' . $sliceTable . ' WHERE article_id=? AND language_id=? AND ctype_id=? AND revision=?', [$articleId, $languageId, $ctype, $sliceRevision]);
                            } else {
                                $prevSlice->setQuery('SELECT * FROM ' . $sliceTable . ' WHERE id=?', [$sliceId]);
                            }

                            $priority = $prevSlice->getValue('priority');

                            $newsql->setValue('article_id', $articleId);
                            $newsql->setValue('module', $moduleKey);
                            $newsql->setValue('language_id', $languageId);
                            $newsql->setValue('ctype_id', $ctype);
                            $newsql->setValue('revision', $sliceRevision);
                            $newsql->setValue('priority', $priority);
                        }

                        if ('edit' == $function) {
                            $newsql->addGlobalUpdateFields();

                            Extension::dispatch(new ExtensionPoint('SLICE_UPDATE', '', [
                                'slice_id' => $sliceId,
                                'article_id' => $articleId,
                                'language_id' => $languageId,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->update();
                            $info = $actionMessage . I18n::msg('block_updated');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $languageId,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_key' => $moduleKey,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = Extension::dispatch(new ExtensionPoint('SLICE_UPDATED', $info, $epParams));
                            $info = Extension::dispatch(new ArticleContentUpdated($OOArt, 'slice_updated', $info));
                        } else {
                            $newsql->addGlobalUpdateFields();
                            $newsql->addGlobalCreateFields();

                            Extension::dispatch(new ExtensionPoint('SLICE_ADD', '', [
                                'article_id' => $articleId,
                                'language_id' => $languageId,
                                'slice_revision' => $sliceRevision,
                            ]));

                            $newsql->insert();
                            $sliceId = $newsql->getLastId();

                            Util::organizePriorities(
                                Core::getTable('article_slice'),
                                'priority',
                                'article_id=' . $articleId . ' AND language_id=' . $languageId . ' AND ctype_id=' . $ctype . ' AND revision=' . (int) $sliceRevision,
                                'priority, updatedate DESC',
                            );

                            $info = $actionMessage . I18n::msg('block_added');
                            $function = '';
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $languageId,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_key' => $moduleKey,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $info = Extension::dispatch(new ExtensionPoint('SLICE_ADDED', $info, $epParams));
                            $info = Extension::dispatch(new ArticleContentUpdated($OOArt, 'slice_added', $info));
                        }
                    } else {
                        // make delete

                        if (ContentHandler::deleteSlice($sliceId)) {
                            $globalInfo = I18n::msg('block_deleted');
                            $epParams = [
                                'article_id' => $articleId,
                                'clang' => $languageId,
                                'function' => $function,
                                'slice_id' => $sliceId,
                                'page' => Controller::getCurrentPage(),
                                'ctype' => $ctype,
                                'category_id' => $categoryId,
                                'module_key' => $moduleKey,
                                'article_revision' => &$articleRevision,
                                'slice_revision' => &$sliceRevision,
                            ];

                            // ----- EXTENSION POINT
                            $globalInfo = Extension::dispatch(new ExtensionPoint('SLICE_DELETED', $globalInfo, $epParams));
                            $globalInfo = Extension::dispatch(new ArticleContentUpdated($OOArt, 'slice_deleted', $globalInfo));
                        } else {
                            $globalWarning = I18n::msg('block_not_deleted');
                        }
                    }
                    // ----- / SAVE SLICE

                    // ----- artikel neu generieren
                    $EA = Sql::factory();
                    $EA->setTable(Core::getTablePrefix() . 'article');
                    $EA->setWhere(['id' => $articleId, 'language_id' => $languageId]);
                    $EA->addGlobalUpdateFields();
                    $EA->update();
                    ArticleCache::delete($articleId, $languageId);

                    Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_ARTICLE_UPDATED', '', [
                        'id' => $articleId,
                        'clang' => $languageId,
                    ]));

                    // ----- POST SAVE ACTION [ADD/EDIT/DELETE]
                    $module->onPostsave($action);
                    if ($messages = $action->messages) {
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

    foreach (count($contentSections) > 1 ? $contentSections : [] as $section) {
        $hasSlice = true;
        if ($ctype != $section->id) {
            $hasSlice = null !== ArticleSlice::getFirstSliceForCtype($section->id, $articleId, $languageId);
        }
        $editPage->addSubpage(new Page('ctype' . $section->id, $section->name)
            ->setHref(['page' => 'content/edit', 'article_id' => $articleId, 'clang' => $languageId, 'ctype' => $section->id])
            ->setIsActive($ctype == $section->id)
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

    $contentNaviRight[] = ['title' => '<a href="' . Url::article($articleId, $languageId) . '" onclick="window.open(this.href); return false;">' . I18n::msg('article_show') . ' <i class="rex-icon rex-icon-external-link"></i></a>'];

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
    $contentMain .= Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_BEFORE_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $languageId,
        'function' => $function,
        'slice_id' => $sliceId,
        'page' => Controller::getCurrentPage(),
        'ctype' => $ctype,
        'category_id' => $categoryId,
        'article_revision' => &$articleRevision,
        'slice_revision' => &$sliceRevision,
    ]));

    // ------------------------------------------ START: MODULE EDITIEREN/ADDEN ETC.
    $contentMain .= Controller::includeCurrentPageSubPath(compact('info', 'warning', 'article', 'articleId', 'categoryId', 'languageId', 'sliceId', 'sliceRevision', 'function', 'ctype', 'context'));
    // ------------------------------------------ END: AUSGABE

    // ----- EXTENSION POINT
    $contentMain .= Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_AFTER_SLICES', '', [
        'article_id' => $articleId,
        'clang' => $languageId,
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
    $contentSidebar = Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_SIDEBAR', '', [
        'article_id' => $articleId,
        'clang' => $languageId,
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
