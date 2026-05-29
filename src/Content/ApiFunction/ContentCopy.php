<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('content_copy')]
class ContentCopy extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('copyContent[]')) {
            throw new ApiFunctionException('User has no permission to copy content!');
        }

        $articleId = Request::request('article_id', 'int');
        $clangA = Request::request('clang_a', 'int');
        $clangB = Request::request('clang_b', 'int');
        $overwrite = Request::request('overwrite', 'bool', false);

        $article = Article::get($articleId, $clangA);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clangA . '"!');
        }

        if (
            !$user->getComplexPerm('clang')->hasPerm($clangA)
            || !$user->getComplexPerm('clang')->hasPerm($clangB)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->getCategoryId())
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if (ContentHandler::copyContent($articleId, $articleId, $clangA, $clangB, null, $overwrite)) {
            return new Result(true, I18n::msg('content_contentcopy'));
        }

        return new Result(false, I18n::msg('content_errorcopy'));
    }
}
