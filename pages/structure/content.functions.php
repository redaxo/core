<?php

use Redaxo\Core\Content\ApiFunction\ArticleCopy;
use Redaxo\Core\Content\ApiFunction\ArticleMove;
use Redaxo\Core\Content\ApiFunction\ArticleToCategory;
use Redaxo\Core\Content\ApiFunction\ArticleToStartArticle;
use Redaxo\Core\Content\ApiFunction\CategoryMove;
use Redaxo\Core\Content\ApiFunction\CategoryToArticle;
use Redaxo\Core\Content\ApiFunction\ContentCopy;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Select\CategorySelect;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

assert(isset($context) && $context instanceof Context);
assert(isset($ctype) && is_int($ctype));
assert(isset($article) && $article instanceof Sql);
assert(isset($categoryId) && is_int($categoryId));
assert(isset($articleId) && is_int($articleId));

$user = Core::requireUser();

$content = '
        <form id="rex-form-content-metamode" action="' . $context->getUrl() . '" method="post" enctype="multipart/form-data" data-pjax-container="#rex-page-main">
            <input type="hidden" name="save" value="1" />
            <input type="hidden" name="ctype" value="' . $ctype . '" />
            ';

$onclickApiFields = static function ($hiddenFields) {
    return 'onclick="$(this.form).append(\'' . escape($hiddenFields) . '\')"';
};

$isStartpage = 1 == $article->getValue('startarticle');
// --------------------------------------------------- ZUM STARTARTICLE MACHEN START
if ($user->hasPerm('article2startarticle[]')) {
    $panel = '<fieldset>';

    $panelClass = 'default';
    $buttons = '';
    if (!$isStartpage && 0 == $article->getValue('parent_id')) {
        $panelClass = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="form-control-static">' . I18n::msg('content_nottostartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } elseif ($isStartpage) {
        $panelClass = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="form-control-static">' . I18n::msg('content_isstartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } else {
        $formElements = [];
        $n = [];
        $n['field'] = '<p class="form-control-static">' . I18n::msg('content_tostartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="article2startarticle" value="1" data-confirm="' . I18n::msg('content_tostartarticle') . '?" ' . $onclickApiFields(ArticleToStartArticle::getHiddenFields()) . '>' . I18n::msg('content_tostartarticle') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
    }

    $panel .= '</fieldset>';

    $fragment = new Fragment();
    $fragment->setVar('class', $panelClass);
    $fragment->setVar('title', I18n::msg('content_startarticle'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}

// --------------------------------------------------- ZUM STARTARTICLE MACHEN END

// --------------------------------------------------- IN KATEGORIE UMWANDELN START
if (!$isStartpage && $user->hasPerm('article2category[]')) {
    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<p class="form-control-static">' . I18n::msg('content_tocategory') . '</p>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="article2category" value="1" data-confirm="' . I18n::msg('content_tocategory') . '?" ' . $onclickApiFields(ArticleToCategory::getHiddenFields()) . '>' . I18n::msg('content_tocategory') . '</button>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('content_category'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// --------------------------------------------------- IN KATEGORIE UMWANDELN END

// --------------------------------------------------- IN ARTIKEL UMWANDELN START
if ($isStartpage && $user->hasPerm('article2category[]') && $user->getComplexPerm('structure')->hasCategoryPerm($article->getValue('parent_id'))) {
    $sql = Sql::factory();
    $sql->setQuery('SELECT pid FROM ' . Core::getTablePrefix() . 'article WHERE parent_id=? LIMIT 1', [$articleId]);
    $emptyCategory = 0 == $sql->getRows();

    $panel = '<fieldset>';

    $panelClass = 'default';
    $buttons = '';
    if (!$emptyCategory) {
        $panelClass = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="form-control-static">' . I18n::msg('content_nottoarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } else {
        $formElements = [];
        $n = [];
        $n['field'] = '<p class="form-control-static">' . I18n::msg('content_toarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="category2article" value="1" data-confirm="' . I18n::msg('content_toarticle') . '?" ' . $onclickApiFields(CategoryToArticle::getHiddenFields()) . '>' . I18n::msg('content_toarticle') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
    }

    $panel .= '</fieldset>';

    $fragment = new Fragment();
    $fragment->setVar('class', $panelClass);
    $fragment->setVar('title', I18n::msg('content_article'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// --------------------------------------------------- IN ARTIKEL UMWANDELN END

// --------------------------------------------------- INHALTE KOPIEREN START
if ($user->hasPerm('copyContent[]') && $user->getComplexPerm('clang')->count() > 1) {
    $clangPerm = $user->getComplexPerm('clang')->getClangs();

    $langA = new Select();
    $langA->setId('clang_a');
    $langA->setName('clang_a');
    $langA->setSize('1');
    $langA->setAttribute('class', 'form-control selectpicker');
    foreach ($clangPerm as $key) {
        $val = I18n::translate(Language::get($key)->getName());
        $langA->addOption($val, $key);
    }

    $langB = new Select();
    $langB->setId('clang_b');
    $langB->setName('clang_b');
    $langB->setSize('1');
    $langB->setAttribute('class', 'form-control selectpicker');
    foreach ($clangPerm as $key) {
        $val = I18n::translate(Language::get($key)->getName());
        $langB->addOption($val, $key);
    }

    $langA->setSelected(Request::request('clang_a', 'int', null));
    $langB->setSelected(Request::request('clang_b', 'int', null));

    $panel = '<fieldset>';

    $grid = [];

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="clang_a">' . I18n::msg('content_contentoflang') . '</label>';
    $n['field'] = $langA->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="clang_b">' . I18n::msg('content_to') . '</label>';
    $n['field'] = $langB->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<div class="checkbox"><label><input type="checkbox" name="overwrite" value="1" /> ' . I18n::msg('content_overwrite') . '</label></div>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="content_copy" value="1" data-confirm="' . I18n::msg('content_submitcopycontent') . '?" ' . $onclickApiFields(ContentCopy::getHiddenFields()) . '>' . I18n::msg('content_submitcopycontent') . '</button>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('content_submitcopycontent'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// --------------------------------------------------- INHALTE KOPIEREN ENDE

// --------------------------------------------------- ARTIKEL VERSCHIEBEN START
if (!$isStartpage && $user->hasPerm('moveArticle[]')) {
    // Wenn Artikel kein Startartikel dann Selectliste darstellen, sonst...
    $moveA = new CategorySelect(false, false, true, !$user->getComplexPerm('structure')->hasMountPoints());
    $moveA->setId('category_id_new');
    $moveA->setName('category_id_new');
    $moveA->setSize('1');
    $moveA->setAttribute('class', 'form-control selectpicker');
    $moveA->setAttribute('data-live-search', 'true');
    $moveA->setSelected($categoryId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_id_new">' . I18n::msg('move_article') . '</label>';
    $n['field'] = $moveA->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="article_move" value="1" data-confirm="' . I18n::msg('content_submitmovearticle') . '?" ' . $onclickApiFields(ArticleMove::getHiddenFields()) . '>' . I18n::msg('content_submitmovearticle') . '</button>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('content_submitmovearticle'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// ------------------------------------------------ ARTIKEL VERSCHIEBEN ENDE

// -------------------------------------------------- ARTIKEL KOPIEREN START
if ($user->hasPerm('copyArticle[]')) {
    $moveA = new CategorySelect(false, false, true, !$user->getComplexPerm('structure')->hasMountPoints());
    $moveA->setName('category_copy_id_new');
    $moveA->setId('category_copy_id_new');
    $moveA->setSize('1');
    $moveA->setAttribute('class', 'form-control selectpicker');
    $moveA->setAttribute('data-live-search', 'true');
    $moveA->setSelected($categoryId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_copy_id_new">' . I18n::msg('copy_article') . '</label>';
    $n['field'] = $moveA->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel = $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="article_copy" value="1" data-confirm="' . I18n::msg('content_submitcopyarticle') . '?" ' . $onclickApiFields(ArticleCopy::getHiddenFields()) . '>' . I18n::msg('content_submitcopyarticle') . '</button>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('content_submitcopyarticle'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// --------------------------------------------------- ARTIKEL KOPIEREN ENDE

// --------------------------------------------------- KATEGORIE/STARTARTIKEL VERSCHIEBEN START
if ($isStartpage && $user->hasPerm('moveCategory[]') && $user->getComplexPerm('structure')->hasCategoryPerm($article->getValue('parent_id'))) {
    $moveA = new CategorySelect(false, false, true, !$user->getComplexPerm('structure')->hasMountPoints());
    $moveA->setId('category_id_new');
    $moveA->setName('category_id_new');
    $moveA->setSize('1');
    $moveA->setAttribute('class', 'form-control selectpicker');
    $moveA->setAttribute('data-live-search', 'true');
    $moveA->setSelected($articleId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_id_new">' . I18n::msg('move_category') . '</label>';
    $n['field'] = $moveA->get();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-send rex-form-aligned" type="submit" name="category_move" value="1" data-confirm="' . I18n::msg('content_submitmovecategory') . '?" ' . $onclickApiFields(CategoryMove::getHiddenFields()) . '>' . I18n::msg('content_submitmovecategory') . '</button>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('content_submitmovecategory'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}

// ------------------------------------------------ KATEGROIE/STARTARTIKEL VERSCHIEBEN ENDE

$content .= '</form>';

return $content;
