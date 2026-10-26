<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceReference;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\ContentPageAfterSlices;
use Redaxo\Core\Content\ExtensionPoint\ContentPageBeforeSlices;
use Redaxo\Core\Content\ExtensionPoint\ContentPageHeader;
use Redaxo\Core\Content\ExtensionPoint\ContentPageSidebar;
use Redaxo\Core\Content\ExtensionPoint\SliceAdding;
use Redaxo\Core\Content\ExtensionPoint\SliceUpdating;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\DatabaseUtil;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$articleId = Request::request('article_id', 'int');
$languageId = Request::request('language', 'int');
$sliceId = Request::request('slice_id', 'int', '');

$articleId = Article::get($articleId) ? $articleId : 0;
$languageId = Language::exists($languageId) ? $languageId : Language::getStartId();

$sliceRevision = 0;

$warning = '';
$globalWarning = '';
$invalidSliceForm = null;
$info = '';
$globalInfo = '';

$article = Sql::factory();
$article->setQuery('
    SELECT article.*, translation.*, category.id IS NOT NULL AS startarticle
    FROM rex_article AS article
    JOIN rex_article_translation AS translation ON translation.article_id = article.id
    LEFT JOIN rex_category AS category ON category.id = article.id
    WHERE
        article.id=?
        AND translation.language_id=?
', [$articleId, $languageId]);

if (1 !== $article->getRows()) {
    echo View::title(I18n::msg('content'), '');
    echo Message::error(I18n::msg('article_doesnt_exist'));
    return;
}

$templateKey = $article->getNullableStringValue('template') ?? '';
$template = Template::get($templateKey);
$contentSections = $template?->getContentSections() ?? [];

$ctype = Request::request('content_section_id', 'int', 1);
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
    'language' => $languageId,
    'content_section_id' => $ctype,
]);

// ----- Titel anzeigen
echo View::title(I18n::msg('content') . ': ' . escape($OOArt->name), '');

// ----- Languages
echo View::languageSwitchAsButtons($context);

// ----- category pfad und rechte
echo View::structureBreadcrumb($categoryId, $articleId, $languageId);

$header = new ContentPageHeader($OOArt, Controller::getCurrentPage(), $ctype, $function, $sliceId, $sliceRevision);
echo Extension::dispatch($header);
$sliceRevision = $header->sliceRevision;

$user = Core::requireUser();

// ----------------- HAT USER DIE RECHTE AN DIESEM ARTICLE ODER NICHT
if (
    !$user->getComplexPerm('language')->hasPerm($languageId)
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

        $sliceTable = 'rex_article_slice';
        $slice = null;
        $priority = 0;
        if ('edit' == $function || 'delete' == $function) {
            // edit/ delete
            // article_id must match: the permission check above is based on the requested article, so slices of
            // other articles (possibly in categories the user has no permission for) must not be addressable here
            $CM = Sql::factory();
            $CM->setQuery('SELECT * FROM ' . $sliceTable . ' WHERE id=? AND article_id=? AND language_id=?', [$sliceId, $articleId, $languageId]);
            if (1 == $CM->getRows()) {
                $slice = ArticleSlice::fromSql($CM);
            }
        } else {
            // add

            // determine priority value to get the new slice into the right order
            $prevSlice = Sql::factory();
            if (-1 == $sliceId) {
                $prevSlice->setQuery('SELECT MAX(priority) as priority FROM ' . $sliceTable . ' WHERE article_id=? AND language_id=? AND content_section_id=? AND revision=?', [$articleId, $languageId, $ctype, $sliceRevision]);
                $priority = ($prevSlice->getNullableIntValue('priority') ?? 0) + 1;
            } else {
                $prevSlice->setQuery('SELECT priority FROM ' . $sliceTable . ' WHERE id=?', [$sliceId]);
                $priority = $prevSlice->getIntValue('priority');
            }

            $slice = ArticleSlice::forNewSlice($articleId, $languageId, $ctype, Request::post('module', 'string'), $priority, $sliceRevision);
        }

        $module = null !== $slice && Module::exists($slice->moduleKey) ? Module::of($slice) : null;

        if (null === $module) {
            // ------------- MODUL IST NICHT VORHANDEN
            $globalWarning = I18n::msg('module_not_found');
            $sliceId = 0;
            $function = '';
        } else {
            // ------------- MODUL IST VORHANDEN
            $moduleKey = $module->slice->moduleKey;
            $category = $categoryId > 0 ? Category::get($categoryId) : null;

            if (!ContentHandler::hasRevisionPerm($user, $module->slice->revision)) {
                $globalWarning = I18n::msg('no_rights_to_this_function');
                $sliceId = 0;
                $function = '';
            } elseif ('delete' != $function && (!Template::checkModuleAllowed($templateKey, $ctype, $moduleKey) || !$module::isAllowedInCategory($category))) {
                // ----- RECHTE AM MODUL ?
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

                $sliceForm = 'delete' === $function ? null : $module->createForm();

                if (null !== $sliceForm && !$module->submitForm($sliceForm)) {
                    $errors = [I18n::msg('slice_saved_error')];
                    foreach ($sliceForm->getFormErrors() as $error) {
                        $errors[] = (string) Html::from($error);
                    }
                    $warning = implode('<br />', $errors);

                    // shown again by the editor, with the submitted values and the errors
                    $invalidSliceForm = $sliceForm;
                } else {
                    // ----- SAVE/UPDATE SLICE
                    if ('add' == $function || 'edit' == $function) {
                        $module->beforeSave();

                        $newsql = Sql::factory();
                        $newsql->setTable($sliceTable);
                        $newsql->setValue('data', $module->encodeData());

                        if ('edit' == $function) {
                            $newsql->setWhere(['id' => $sliceId]);
                        } else {
                            $newsql->setValue('article_id', $articleId);
                            $newsql->setValue('module', $moduleKey);
                            $newsql->setValue('language_id', $languageId);
                            $newsql->setValue('content_section_id', $ctype);
                            $newsql->setValue('revision', $sliceRevision);
                            $newsql->setValue('priority', $priority);
                        }

                        if ('edit' == $function) {
                            $newsql->addGlobalUpdateFields();

                            Extension::dispatch(new SliceUpdating(Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $languageId, $sliceRevision))));

                            $newsql->update();

                            $slice = Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $languageId, $sliceRevision));
                            $info = Extension::dispatch(new ArticleContentUpdated($OOArt, 'slice_updated', $slice, I18n::msg('block_updated')));
                        } else {
                            $newsql->addGlobalUpdateFields();
                            $newsql->addGlobalCreateFields();

                            Extension::dispatch(new SliceAdding($OOArt, $ctype, $moduleKey, $sliceRevision));

                            $newsql->insert();
                            $sliceId = $newsql->getLastId();

                            DatabaseUtil::organizePriorities(
                                'rex_article_slice',
                                'priority',
                                'article_id=' . $articleId . ' AND language_id=' . $languageId . ' AND content_section_id=' . $ctype . ' AND revision=' . $sliceRevision,
                                'priority, updatedate DESC',
                            );

                            $function = '';
                            $slice = Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $languageId, $sliceRevision));
                            $info = Extension::dispatch(new ArticleContentUpdated($OOArt, 'slice_added', $slice, I18n::msg('block_added')));
                        }

                        ArticleSliceReference::update($slice);
                        Module::of($slice)->afterSave();
                    } else {
                        // make delete

                        ContentHandler::deleteSlice($sliceId);
                        $globalInfo = I18n::msg('block_deleted');
                    }
                    // ----- / SAVE SLICE

                    // ----- artikel neu generieren
                    $EA = Sql::factory();
                    $EA->setTable('rex_article');
                    $EA->setWhere(['id' => $articleId]);
                    $EA->addGlobalUpdateFields();
                    $EA->update();
                    ArticleCache::delete($articleId, $languageId);

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
            $hasSlice = null !== ArticleSlice::getFirstSliceForContentSection($section->id, $articleId, $languageId);
        }
        $editPage->addSubpage(new Page('content_section' . $section->id, $section->name)
            ->setHref(['page' => 'content/edit', 'article_id' => $articleId, 'language' => $languageId, 'content_section_id' => $section->id])
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
            || ($user->hasPerm('copyContent[]') && $user->getComplexPerm('language')->count() > 1)
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

    $contentNaviRight[] = [
        'title' => I18n::msg('article_show') . ' <i class="rex-icon rex-icon-external-link"></i>',
        'href' => Url::article($articleId, $languageId),
        'linkAttr' => ['target' => '_blank', 'rel' => 'noopener'],
    ];

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

    $beforeSlices = new ContentPageBeforeSlices($OOArt, Controller::getCurrentPage(), $ctype, $function, $sliceId, $sliceRevision);
    $contentMain .= Extension::dispatch($beforeSlices);
    $sliceRevision = $beforeSlices->sliceRevision;

    // ------------------------------------------ START: MODULE EDITIEREN/ADDEN ETC.
    $contentMain .= Controller::includeCurrentPageSubPath(compact('info', 'warning', 'invalidSliceForm', 'article', 'articleId', 'categoryId', 'languageId', 'sliceId', 'sliceRevision', 'function', 'ctype', 'context'));
    // ------------------------------------------ END: AUSGABE

    $contentMain .= Extension::dispatch(new ContentPageAfterSlices($OOArt, Controller::getCurrentPage(), $ctype, $function, $sliceId, $sliceRevision));

    $contentMain = '<section id="rex-js-page-main-content" data-pjax-container="#rex-js-page-main-content">' . $contentMain . '</section>';

    // own scope, the sidebar must not overwrite the variables of this page
    $metaSidebar = Type::string((static fn (int $articleId, int $languageId, int $ctype): mixed => include Path::core('pages/structure/content.metainfo.php'))($articleId, $languageId, $ctype));

    $contentSidebar = Extension::dispatch(new ContentPageSidebar($OOArt, Controller::getCurrentPage(), $ctype, $function, $sliceId, $sliceRevision, $metaSidebar));

    $fragment = new Fragment();
    $fragment->setVar('content', $contentMain, false);
    $fragment->setVar('sidebar', $contentSidebar, false);

    echo $fragment->parse('core/page/main_content.php');
}
