<?php

namespace Redaxo\Core\Content\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;

/**
 * @internal
 */
#[AsApiFunction('article_slice_move')]
class ArticleSliceMove extends ApiFunction
{
    public function execute()
    {
        $articleId = Request::request('article_id', 'int');
        $clang = Request::request('clang', 'int');
        $sliceId = Request::request('slice_id', 'int');
        $direction = Request::request('direction', 'string');

        $ooArt = Article::get($articleId, $clang);
        if (!$ooArt instanceof Article) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }
        $categoryId = $ooArt->getCategoryId();

        $user = Core::requireUser();

        // check permissions
        if (!$user->hasPerm('moveSlice[]')) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        if (!$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }

        // modul und rechte vorhanden ?
        $CM = Sql::factory();
        $CM->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice left join ' . Core::getTablePrefix() . 'module on ' . Core::getTablePrefix() . 'article_slice.module_id=' . Core::getTablePrefix() . 'module.id where ' . Core::getTablePrefix() . 'article_slice.id=? and clang_id=?', [$sliceId, $clang]);
        if (1 != $CM->getRows()) {
            throw new ApiFunctionException(I18n::msg('module_not_found'));
        }
        $moduleId = (int) $CM->getValue(Core::getTablePrefix() . 'article_slice.module_id');

        // ----- RECHTE AM MODUL ?
        if ($user->getComplexPerm('modules')->hasPerm($moduleId)) {
            $message = ContentHandler::moveSlice($sliceId, $clang, $direction);
        } else {
            throw new ApiFunctionException(I18n::msg('no_rights_to_this_function'));
        }
        return new Result(true, $message);
    }
}
