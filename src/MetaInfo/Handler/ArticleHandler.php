<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

/**
 * @internal
 */
final class ArticleHandler extends AbstractHandler
{
    /**
     * Renders (and on save persists) the article meta form.
     *
     * @param array{id: int, clang: int, article: object} $params
     */
    public function getForm(array $params): string
    {
        $ooArt = Article::get($params['id'], $params['clang']);
        $categoryId = $ooArt->categoryId ?? 0;
        $category = $categoryId > 0 ? Category::get($categoryId, $params['clang']) : null;

        $ctx = new MetaContext(MetaEntity::Article, $params['article'], $category);

        // Only save when the meta form was actually submitted (e.g. not when navigating via be_search).
        if (Request::post('savemeta', 'boolean')) {
            $ctx = $this->save($params, $ctx);
        }

        return $this->renderFields($ctx);
    }

    /** @param array{id: int, clang: int, article: object} $params */
    private function save(array $params, MetaContext $ctx): MetaContext
    {
        $id = $params['id'];
        $clang = $params['clang'];

        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'article');
        $sql->setWhere('id=:id AND clang_id=:clang', ['id' => $id, 'clang' => $clang]);
        $sql->setValue('name', Request::post('meta_article_name', 'string'));

        $saved = $this->saveRequestValues($sql, $ctx);

        if ($sql->hasValues()) {
            $sql->addGlobalUpdateFields();
            $sql->update();
        }

        ArticleCache::deleteMeta($id, $clang);

        Extension::dispatch(new ExtensionPoint('ART_META_UPDATED', '', $params));

        // Redisplay the freshly submitted values.
        return new MetaContext($ctx->entity, $ctx->subject, $ctx->category, $ctx->mediaCategory, $saved);
    }

    public function extendForm(ExtensionPoint $ep): string
    {
        // noop — the article form is rendered directly via getForm()
        return '';
    }
}
