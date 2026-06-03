<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_edit')]
final class ArticleEdit extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('editArticle[]')) {
            throw new ApiFunctionException('User has no permission to edit articles!');
        }

        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');

        $article = Article::get($articleId, $clang);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clang)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->categoryId)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        $data = [];
        $data['priority'] = Request::post('article-position', 'int');
        $data['name'] = Request::post('article-name', 'string');
        $data['template'] = Request::post('template', 'string');
        return new Result(true, ArticleHandler::editArticle($articleId, $clang, $data));
    }
}
