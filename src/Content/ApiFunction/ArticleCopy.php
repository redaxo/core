<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_copy')]
final class ArticleCopy extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('copyArticle[]')) {
            throw new ApiFunctionException('User has no permission to copy articles!');
        }

        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int', 1);
        // The destination category in which the given article will be copied
        $categoryCopyIdNew = Request::request('category_copy_id_new', 'int');

        $article = Article::get($articleId);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '"!');
        }

        if (0 !== $categoryCopyIdNew && null === Category::get($categoryCopyIdNew)) {
            throw new ApiFunctionException('Unable to find category with id "' . $categoryCopyIdNew . '"!');
        }

        if (
            !$user->getComplexPerm('structure')->hasCategoryPerm($article->categoryId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($categoryCopyIdNew)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'clang' => $clang,
        ]);

        if (false !== ($newId = ArticleHandler::copyArticle($articleId, $categoryCopyIdNew))) {
            $result = new Result(true, I18n::msg('content_articlecopied'));
            Response::sendRedirect($context->getUrl([
                'article_id' => $newId,
                'info' => $result->message,
            ]));
        }

        return new Result(false, I18n::msg('content_errorcopyarticle'));
    }
}
