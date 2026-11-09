<?php

use Redaxo\Core\Content\ApiFunction\ArticleCopy;
use Redaxo\Core\Content\ApiFunction\ArticleMove;
use Redaxo\Core\Content\ApiFunction\ArticleToCategory;
use Redaxo\Core\Content\ApiFunction\ArticleToStartArticle;
use Redaxo\Core\Content\ApiFunction\CategoryMove;
use Redaxo\Core\Content\ApiFunction\CategoryToArticle;
use Redaxo\Core\Content\ApiFunction\ContentCopy;
use Redaxo\Core\Content\CategoryChoices;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Widget\SelectInput;
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
        <form id="rex-form-content-metamode" action="' . $context->getUrl() . '" method="post" enctype="multipart/form-data">
            <input type="hidden" name="save" value="1" />
            <input type="hidden" name="content_section_id" value="' . $ctype . '" />
            ';

/** @param array<string, string> $params */
$apiParams = static fn (array $params): string => 'data-rex-submit-params="' . escape(json_encode($params, JSON_THROW_ON_ERROR)) . '"';

$isStartpage = $article->getBoolValue('startarticle');
// --------------------------------------------------- ZUM STARTARTICLE MACHEN START
if ($user->hasPerm('article2startarticle[]')) {
    $panel = '<fieldset>';

    $panelVariant = 'default';
    $buttons = '';
    if (!$isStartpage && null === $article->getNullableIntValue('parent_id')) {
        $panelVariant = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_nottostartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } elseif ($isStartpage) {
        $panelVariant = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_isstartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } else {
        $formElements = [];
        $n = [];
        $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_tostartarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="article2startarticle" value="1" data-confirm="' . I18n::msg('content_tostartarticle') . '?" ' . $apiParams(ArticleToStartArticle::getUrlParams()) . '>' . I18n::msg('content_tostartarticle') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
    }

    $panel .= '</fieldset>';

    $fragment = new Fragment();
    $fragment->setVar('class', $panelVariant);
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
    $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_tocategory') . '</p>';
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="article2category" value="1" data-confirm="' . I18n::msg('content_tocategory') . '?" ' . $apiParams(ArticleToCategory::getUrlParams()) . '>' . I18n::msg('content_tocategory') . '</button>';
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
if ($isStartpage && $user->hasPerm('article2category[]') && $user->getComplexPerm('structure')->hasCategoryPerm($article->getNullableIntValue('parent_id'))) {
    $sql = Sql::factory();
    $sql->setQuery('SELECT id FROM rex_article WHERE parent_id=? LIMIT 1', [$articleId]);
    $emptyCategory = 0 == $sql->getRows();

    $panel = '<fieldset>';

    $panelVariant = 'default';
    $buttons = '';
    if (!$emptyCategory) {
        $panelVariant = 'info';

        $formElements = [];
        $n = [];
        $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_nottoarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');
    } else {
        $formElements = [];
        $n = [];
        $n['field'] = '<p class="rex-field-value">' . I18n::msg('content_toarticle') . '</p>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="category2article" value="1" data-confirm="' . I18n::msg('content_toarticle') . '?" ' . $apiParams(CategoryToArticle::getUrlParams()) . '>' . I18n::msg('content_toarticle') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');
    }

    $panel .= '</fieldset>';

    $fragment = new Fragment();
    $fragment->setVar('class', $panelVariant);
    $fragment->setVar('title', I18n::msg('content_article'), false);
    $fragment->setVar('body', $panel, false);
    $fragment->setVar('buttons', $buttons, false);
    $content .= $fragment->parse('core/page/section.php');
}
// --------------------------------------------------- IN ARTIKEL UMWANDELN END

// --------------------------------------------------- INHALTE KOPIEREN START
if ($user->hasPerm('copyContent[]') && $user->getComplexPerm('language')->count() > 1) {
    $languagePerm = $user->getComplexPerm('language')->getLanguageIds();

    $languageChoices = [];
    foreach ($languagePerm as $key) {
        $languageChoices[$key] = I18n::translate(Language::require($key)->name);
    }

    $langA = new SelectInput('language_a', $languageChoices, Request::request('language_a', 'string'), 'language_a');
    $langB = new SelectInput('language_b', $languageChoices, Request::request('language_b', 'string'), 'language_b');

    $panel = '<fieldset>';

    $grid = [];

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="language_a">' . I18n::msg('content_contentoflang') . '</label>';
    $n['field'] = $langA->render();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('group', true);
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="language_b">' . I18n::msg('content_to') . '</label>';
    $n['field'] = $langB->render();
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
    $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="content_copy" value="1" data-confirm="' . I18n::msg('content_submitcopycontent') . '?" ' . $apiParams(ContentCopy::getUrlParams()) . '>' . I18n::msg('content_submitcopycontent') . '</button>';
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

$categoryInput = static fn (string $name, int $selected): SelectInput => new SelectInput(
    $name,
    new CategoryChoices(root: true)->choices(),
    (string) $selected,
    $name,
    search: true,
);

// --------------------------------------------------- ARTIKEL VERSCHIEBEN START
if (!$isStartpage && $user->hasPerm('moveArticle[]')) {
    // Wenn Artikel kein Startartikel dann Selectliste darstellen, sonst...
    $moveA = $categoryInput('category_id_new', $categoryId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_id_new">' . I18n::msg('move_article') . '</label>';
    $n['field'] = $moveA->render();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="article_move" value="1" data-confirm="' . I18n::msg('content_submitmovearticle') . '?" ' . $apiParams(ArticleMove::getUrlParams()) . '>' . I18n::msg('content_submitmovearticle') . '</button>';
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
    $moveA = $categoryInput('category_copy_id_new', $categoryId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_copy_id_new">' . I18n::msg('copy_article') . '</label>';
    $n['field'] = $moveA->render();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel = $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="article_copy" value="1" data-confirm="' . I18n::msg('content_submitcopyarticle') . '?" ' . $apiParams(ArticleCopy::getUrlParams()) . '>' . I18n::msg('content_submitcopyarticle') . '</button>';
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
if ($isStartpage && $user->hasPerm('moveCategory[]') && $user->getComplexPerm('structure')->hasCategoryPerm($article->getNullableIntValue('parent_id'))) {
    $moveA = $categoryInput('category_id_new', $articleId);

    $panel = '<fieldset>';

    $formElements = [];
    $n = [];
    $n['label'] = '<label for="category_id_new">' . I18n::msg('move_category') . '</label>';
    $n['field'] = $moveA->render();
    $formElements[] = $n;

    $fragment = new Fragment();
    $fragment->setVar('elements', $formElements, false);
    $panel .= $fragment->parse('core/form/form.php');

    $panel .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="rex-button rex-form-aligned" data-variant="primary" type="submit" name="category_move" value="1" data-confirm="' . I18n::msg('content_submitmovecategory') . '?" ' . $apiParams(CategoryMove::getUrlParams()) . '>' . I18n::msg('content_submitmovecategory') . '</button>';
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
