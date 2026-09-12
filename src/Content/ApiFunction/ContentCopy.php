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
final class ContentCopy extends ApiFunction
{
    public function execute(): Result
    {
        $user = Core::requireUser();
        if (!$user->hasPerm('copyContent[]')) {
            throw new ApiFunctionException('User has no permission to copy content!');
        }

        $articleId = Request::request('article_id', 'int');
        $fromLanguageId = Request::request('language_a', 'int');
        $toLanguageId = Request::request('language_b', 'int');
        $overwrite = Request::request('overwrite', 'bool', false);

        $article = Article::get($articleId, $fromLanguageId);
        if (!$article instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and language "' . $fromLanguageId . '"!');
        }

        if (
            !$user->getComplexPerm('language')->hasPerm($fromLanguageId)
            || !$user->getComplexPerm('language')->hasPerm($toLanguageId)
            || !$user->getComplexPerm('structure')->hasCategoryPerm($article->categoryId)
        ) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if (ContentHandler::copyContent($articleId, $articleId, $fromLanguageId, $toLanguageId, null, $overwrite)) {
            return new Result(true, I18n::msg('content_contentcopy'));
        }

        return new Result(false, I18n::msg('content_errorcopy'));
    }
}
