<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_copy')]
class ArticleCopy extends ApiFunction
{
    public function execute(): Result
    {
        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int', 1);
        // The destination category in which the given article will be copied
        $categoryCopyIdNew = Request::request('category_copy_id_new', 'int');
        $user = Core::requireUser();

        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'clang' => $clang,
        ]);

        if ($user->hasPerm('copyArticle[]') && $user->getComplexPerm('structure')->hasCategoryPerm($categoryCopyIdNew)) {
            if (false !== ($newId = ArticleHandler::copyArticle($articleId, $categoryCopyIdNew))) {
                $result = new Result(true, I18n::msg('content_articlecopied'));
                Response::sendRedirect($context->getUrl([
                    'article_id' => $newId,
                    'info' => $result->message,
                ]));
            } else {
                $result = new Result(false, I18n::msg('content_errorcopyarticle'));
            }

            return $result;
        }

        throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
    }
}
