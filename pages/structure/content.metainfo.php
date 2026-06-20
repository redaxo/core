<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\ApiFunction\ArticleStatusChange;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\StructureContext;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\Handler\ArticleHandler as MetaInfoArticleHandler;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($ep) && $ep instanceof ExtensionPoint);

$params = $ep->getParams();

$articleId = (int) $params['article_id'];
$clang = (int) $params['clang'];

$content = [];

$article = Article::get($articleId, $clang);
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
    clangId: Request::request('clang', 'int'),
);

if (0 == $article->getValue('startarticle')) {
    if (Core::requireUser()->hasPerm('publishArticle[]')) {
        if (count($articleStatusTypes) > 2) {
            $articleStatus = '<div class="dropdown"><a href="#" class="dropdown-toggle ' . $articleClass . '" type="button" data-toggle="dropdown"><i class="rex-icon ' . $articleIcon . '"></i>&nbsp;' . $articleStatus . '&nbsp;<span class="caret"></span></a><ul class="dropdown-menu dropdown-menu-right">';
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
            SELECT article.*
            FROM ' . Core::getTablePrefix() . "article as article
            WHERE
                article.id='$articleId'
                AND clang_id=$clang",
);

if (1 == $article->getRows()) {
    $template = Template::get((string) $article->getValue('template'));

    $ctype = Request::request('ctype', 'int', 1);
    if ($ctype < 1 || !$template?->hasContentSection($ctype)) {
        $ctype = 1;
    }

    $context = new Context([
        'page' => Controller::getCurrentPage(),
        'article_id' => $articleId,
        'clang' => $clang,
        'ctype' => $ctype,
    ]);

    $metainfoHandler = new MetaInfoArticleHandler();
    $form = $metainfoHandler->getForm([
        'id' => $articleId,
        'clang' => $clang,
        'article' => $article,
    ]);

    // The article name is not a meta field, but it is offered in the same form. Render it with the same
    // markup as the meta fields (see MetaField::render) so it lines up with them instead of using the
    // legacy horizontal form fragment.
    $articleName = '<div class="form-group">'
        . '<label for="rex-id-meta-article-name">' . escape(I18n::msg('header_article_name')) . '</label>'
        . '<input class="form-control" type="text" id="rex-id-meta-article-name" name="meta_article_name" value="' . escape(Article::require($articleId, $clang)->name) . '">'
        . '</div>';
    $form = $articleName . $form;

    $content[] = '
              <div id="rex-page-sidebar-metainfo" data-pjax-container="#rex-page-sidebar-metainfo">
                <form class="metainfo-sidebar" action="' . $context->getUrl() . '" method="post" enctype="multipart/form-data">
                    ' . (Request::post('savemeta', 'boolean') ? Message::success(I18n::msg('minfo_metadata_saved')) : '') . '
                    <fieldset>
                        <input type="hidden" name="save" value="1" />
                        <input type="hidden" name="ctype" value="' . $ctype . '" />
                        ' . $form . '
                        <button class="btn btn-primary pull-left" type="submit" name="savemeta"' . Core::getAccesskey(I18n::msg('update_metadata'), 'save') . ' value="1">' . I18n::msg('update_metadata') . '</button>
                    </fieldset>
                </form>
              </div>
                ';
}

// ------------------

$fragment = new Fragment();
$fragment->setVar('title', '<i class="rex-icon rex-icon-info"></i> ' . I18n::msg('metadata'), false);
$fragment->setVar('body', implode('', $content), false);
$fragment->setVar('article_id', $params['article_id'], false);
$fragment->setVar('clang', $params['clang'], false);
$fragment->setVar('ctype', $params['ctype'], false);
$fragment->setVar('collapse', true);
$fragment->setVar('collapsed', false);
return $fragment->parse('core/page/section.php');
