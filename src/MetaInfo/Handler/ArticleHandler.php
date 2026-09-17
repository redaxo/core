<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\Category;
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
     * @param array{id: int, language: int, article: object} $params
     */
    public function getForm(array $params): string
    {
        $ooArt = Article::get($params['id'], $params['language']);
        $categoryId = $ooArt->categoryId ?? 0;
        $category = $categoryId > 0 ? Category::get($categoryId, $params['language']) : null;

        $context = new MetaContext(MetaEntity::Article, $params['article'], $category);

        // Only save when the meta form was actually submitted (e.g. not when navigating via be_search).
        if (Request::post('savemeta', 'boolean') && self::getCsrfToken()->isValid()) {
            $context = $this->save($params, $context);
        }

        return $this->renderFields($context);
    }

    /** @param array{id: int, language: int, article: object} $params */
    private function save(array $params, MetaContext $context): MetaContext
    {
        $id = $params['id'];
        $languageId = $params['language'];

        $translation = Sql::factory();
        $translation->setTable('rex_article_translation');
        $translation->setWhere(['article_id' => $id, 'language_id' => $languageId]);
        $translation->setValue('name', Request::post('meta_article_name', 'string'));
        $saved = $this->saveRequestValues($translation, $context, translatable: true);
        $translation->update();

        $shared = Sql::factory();
        $shared->setTable('rex_article');
        $shared->setWhere(['id' => $id]);
        $saved += $this->saveRequestValues($shared, $context, translatable: false);
        $shared->addGlobalUpdateFields();
        $shared->update();

        ArticleCache::deleteMeta($id);

        Extension::dispatch(new ExtensionPoint('ART_META_UPDATED', '', $params));

        // Redisplay the freshly submitted values.
        return new MetaContext($context->entity, $context->subject, $context->category, $context->mediaCategory, $saved);
    }

    /** @param ExtensionPoint<string> $ep */
    public function extendForm(ExtensionPoint $ep): string
    {
        // noop — the article form is rendered directly via getForm()
        return '';
    }
}
