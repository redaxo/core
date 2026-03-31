<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
class ArticleHandler extends AbstractHandler
{
    public const PREFIX = 'art_';

    /** @return array */
    protected function handleSave(array $params, Sql $sqlFields)
    {
        // Nur speichern wenn auch das MetaForm ausgefüllt wurde
        // z.b. nicht speichern wenn über be_search select navigiert wurde
        if (!Request::post('savemeta', 'boolean')) {
            return $params;
        }

        $article = Sql::factory();
        // $article->setDebug();
        $article->setTable(Core::getTablePrefix() . 'article');
        $article->setWhere('id=:id AND clang_id=:clang', ['id' => $params['id'], 'clang' => $params['clang']]);
        $article->setValue('name', Request::post('meta_article_name', 'string'));

        parent::fetchRequestValues($params, $article, $sqlFields);

        // do the save only when metafields are defined
        if ($article->hasValues()) {
            $article->addGlobalUpdateFields();
            $article->update();
        }

        ArticleCache::deleteMeta($params['id'], $params['clang']);

        Extension::registerPoint(new ExtensionPoint('ART_META_UPDATED', '', $params));

        return $params;
    }

    /** @return string */
    protected function buildFilterCondition(array $params)
    {
        $restrictionsCondition = '';

        if (!empty($params['id'])) {
            $s = '';
            $OOArt = Article::get($params['id'], $params['clang']);

            // Alle Metafelder des Pfades sind erlaubt
            foreach ($OOArt->getPathAsArray() as $pathElement) {
                if ('' != $pathElement) {
                    $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                }
            }

            $t = ' OR `p`.`templates` LIKE "%|' . $OOArt->getValue('template_id') . '|%"';

            $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ') AND (`p`.`templates` = "" OR `p`.`templates` IS NULL ' . $t . ')';
        }

        return $restrictionsCondition;
    }

    protected function renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $inputType)
    {
        return $field;
    }

    /** @return string */
    public function getForm(array $params)
    {
        $OOArt = Article::get($params['id'], $params['clang']);

        $params['activeItem'] = $params['article'];
        // Hier die category_id setzen, damit beim klick auf den REX_LINK_BUTTON der Medienpool in der aktuellen Kategorie startet
        $params['activeItem']->setValue('category_id', $OOArt->getCategoryId());

        return parent::renderFormAndSave(self::PREFIX, $params);
    }

    public function extendForm(ExtensionPoint $ep)
    {
        // noop
        return '';
    }
}
