<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
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
    /**
     * @throws ApiFunctionException
     *
     * @return Result
     */
    public function execute()
    {
        $articleId = Request::request('article_id', 'int');
        $clangA = Request::request('clang_a', 'int');
        $clangB = Request::request('clang_b', 'int');
        $overwrite = Request::request('overwrite', 'bool', false);

        $user = Core::requireUser();

        // Check permissions
        if (
            $user->hasPerm('copyContent[]')
            && $user->getComplexPerm('clang')->hasPerm($clangA)
            && $user->getComplexPerm('clang')->hasPerm($clangB)
        ) {
            if (ContentHandler::copyContent($articleId, $articleId, $clangA, $clangB, null, $overwrite)) {
                return new Result(true, I18n::msg('content_contentcopy'));
            }

            return new Result(true, I18n::msg('content_errorcopy'));
        }

        throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
    }
}
