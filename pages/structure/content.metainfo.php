<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\ApiFunction\ArticleStatusChange;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureContext;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Form\FieldRow;
use Redaxo\Core\Form\Widget\Input;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($articleId) && is_int($articleId));
assert(isset($languageId) && is_int($languageId));
assert(isset($ctype) && is_int($ctype));

$content = [];

$article = Article::get($articleId, $languageId);
$articleStatusTypes = ArticleHandler::statusTypes();
$status = (int) $article->getValue('status');

// ------------------

$panels = [];
$panels[] = '<dt>' . I18n::msg('created_by') . '</dt><dd>' . escape($article->getValue('createuser')) . '</dd>';
$panels[] = '<dt>' . I18n::msg('created_on') . '</dt><dd>' . Formatter::intlDate($article->getValue('createdate')) . '</dd>';
$panels[] = '<dt>' . I18n::msg('updated_by') . '</dt><dd>' . escape($article->getValue('updateuser')) . '</dd>';
$panels[] = '<dt>' . I18n::msg('updated_on') . '</dt><dd>' . Formatter::intlDate($article->getValue('updatedate')) . '</dd>';

$articleClass = $articleStatusTypes[$status][1];
$articleStatus = $articleStatusTypes[$status][0];
$articleIcon = $articleStatusTypes[$status][2];
$structureContext = new StructureContext(
    categoryId: $article->categoryId ?? 0,
    articleId: Request::request('article_id', 'int'),
    languageId: Request::request('language', 'int'),
);

if (0 == $article->getValue('startarticle')) {
    if (Core::requireUser()->hasPerm('publishArticle[]')) {
        if (count($articleStatusTypes) > 2) {
            $articleStatus = '<div class="rex-dropdown"><button type="button" class="rex-dropdown-link ' . $articleClass . '" data-rex-dropdown aria-expanded="false"><i class="rex-icon ' . $articleIcon . '" aria-hidden="true"></i>&nbsp;' . $articleStatus . '&nbsp;<span class="rex-caret" aria-hidden="true"></span></button><ul class="rex-dropdown-menu" data-align="end">';
            foreach ($articleStatusTypes as $artStatusKey => $artStatusType) {
                $articleStatus .= '<li><a  class="' . $artStatusType[1] . '" href="' . $structureContext->getContext()->getUrl([
                    'article_id' => $articleId,
                    'page' => 'content/edit',
                    'mode' => 'edit',
                    'art_status' => $artStatusKey,
                ] + ArticleStatusChange::getUrlParams()) . '">' . $artStatusType[0] . '</a></li>';
            }
            $articleStatus .= '</ul></div>';
        } else {
            $articleStatus = '<a class="' . $articleClass . '" href="' . $structureContext->getContext()->getUrl([
                'article_id' => $articleId,
                'page' => 'content/edit',
                'mode' => 'edit',
            ] + ArticleStatusChange::getUrlParams()) . '"><i class="rex-icon ' . $articleIcon . '"></i>&nbsp;' . $articleStatus . '</a>';
        }
    } else {
        $articleStatus = '<span class="' . $articleClass . ' text-muted"><i class="rex-icon ' . $articleIcon . '"></i> ' . $articleStatus . '</span>';
    }
}

$panels[] = '<dt>' . I18n::msg('status') . '</dt><dd class="' . $articleStatusTypes[$status][1] . '">' . $articleStatus . '</dd>';

$content[] = '<dl class="dl-horizontal text-left">' . implode('', $panels) . '</dl>';

// ------------------

$article = Sql::factory();
$article->setQuery('
    SELECT article.*, translation.*, category.id IS NOT NULL AS startarticle
    FROM rex_article AS article
    JOIN rex_article_translation AS translation ON translation.article_id = article.id
    LEFT JOIN rex_category AS category ON category.id = article.id
    WHERE
        article.id = ?
        AND translation.language_id = ?
', [$articleId, $languageId]);

if (1 == $article->getRows()) {
    $template = Template::get($article->getNullableStringValue('template') ?? '');

    $ctype = Request::request('content_section_id', 'int', 1);
    if ($ctype < 1 || !$template?->hasContentSection($ctype)) {
        $ctype = 1;
    }

    $context = new Context([
        'page' => Controller::getCurrentPage(),
        'article_id' => $articleId,
        'language' => $languageId,
        'content_section_id' => $ctype,
    ]);

    $editedArticle = Article::require($articleId, $languageId);
    $categoryId = $editedArticle->categoryId ?? 0;
    $meta = new MetaForm(new MetaContext(MetaEntity::Article, $editedArticle, $categoryId > 0 ? Category::get($categoryId, $languageId) : null));
    $articleName = $editedArticle->name;

    $csrfToken = CsrfToken::factory('metainfo');

    $message = '';
    if (Request::post('savemeta', 'boolean')) {
        $articleName = Request::post('meta_article_name', 'string');

        if (!$csrfToken->isValid()) {
            $message = Message::error(I18n::msg('csrf_token_invalid'));
        } elseif (!$meta->submit()) {
            $message = Message::error(I18n::msg('minfo_metadata_invalid'));
        } else {
            try {
                ArticleHandler::edit($articleId, $languageId, ['name' => $articleName], $meta->values());
                $message = Message::success(I18n::msg('minfo_metadata_saved'));
            } catch (UserMessageException $e) {
                $message = Message::error($e->getMessage());
            }
        }
    }

    // The article name is not a meta field, but it is offered in the same form, with the same markup.
    $form = (string) new FieldRow(
        new Input('meta_article_name', $articleName, 'rex-id-meta-article-name'),
        I18n::msg('header_article_name'),
        vertical: true,
    )->render();
    $form .= $meta->render(vertical: true);

    $content[] = '
              <div id="rex-page-sidebar-metainfo">
                <form class="metainfo-sidebar" action="' . $context->getUrl() . '" method="post" enctype="multipart/form-data">
                    ' . $message . '
                    <fieldset>
                        <input type="hidden" name="save" value="1" />
                        <input type="hidden" name="content_section_id" value="' . $ctype . '" />
                        ' . $csrfToken->getHiddenField() . '
                        ' . $form . '
                        <button class="rex-button pull-left" data-variant="primary" type="submit" name="savemeta"' . Accesskey::attributes(I18n::msg('update_metadata'), 'save') . ' value="1">' . I18n::msg('update_metadata') . '</button>
                    </fieldset>
                </form>
              </div>
                ';
}

// ------------------

$fragment = new Fragment();
$fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . I18n::msg('metadata'), false);
$fragment->setVar('body', implode('', $content), false);
$fragment->setVar('article_id', $articleId, false);
$fragment->setVar('language', $languageId, false);
$fragment->setVar('ctype', $ctype, false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', false);
return $fragment->parse('core/page/section.php');
