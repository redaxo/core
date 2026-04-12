<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
class CategoryHandler extends AbstractHandler
{
    public const PREFIX = 'cat_';
    public const CONTAINER = 'rex-structure-category-metainfo';

    /** @return string */
    public function renderToggleButton(ExtensionPoint $ep)
    {
        $restrictionsCondition = $this->buildFilterCondition($ep->getParams());

        $fields = parent::getSqlFields(self::PREFIX, $restrictionsCondition);
        if ($fields->getRows() >= 1) {
            $return = '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';

            return $ep->getSubject() . $return;
        }

        return $ep->getSubject();
    }

    /** @return array */
    public function handleSave(array $params, Sql $sqlFields)
    {
        if ('post' != Request::requestMethod()) {
            return $params;
        }

        $article = Sql::factory();
        // $article->setDebug();
        $article->setTable(Core::getTablePrefix() . 'article');
        $article->setWhere('id=:id AND clang_id=:clang', ['id' => $params['id'], 'clang' => $params['clang']]);

        parent::fetchRequestValues($params, $article, $sqlFields);

        // do the save only when metafields are defined
        if ($article->hasValues()) {
            $article->update();
        }

        // Artikel nochmal mit den zusätzlichen Werten neu generieren
        ArticleCache::generateMeta($params['id'], $params['clang']);

        return $params;
    }

    /** @return string */
    protected function buildFilterCondition(array $params)
    {
        $s = '';

        if (!empty($params['id'])) {
            $OOCat = Category::get($params['id'], $params['clang']);

            // Alle Metafelder des Pfades sind erlaubt
            foreach ($OOCat->getPathAsArray() as $pathElement) {
                if ('' != $pathElement) {
                    $s .= ' OR FIND_IN_SET(' . $pathElement . ', `p`.`restrictions`)';
                }
            }

            // Auch die Kategorie selbst kann Metafelder haben
            $s .= ' OR FIND_IN_SET(' . $params['id'] . ', `p`.`restrictions`)';
        }

        return 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';
    }

    public function renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $inputType)
    {
        if ('legend' == $inputType) {
            return '<h3 class="form-legend">' . $label . '</h3>';
        }

        return $field;
    }

    public function extendForm(ExtensionPoint $ep)
    {
        $params = $ep->getParams();
        if (isset($params['category'])) {
            $params['activeItem'] = $params['category'];

            // Hier die category_id setzen, damit beim klick auf den REX_LINK_BUTTON der Medienpool in der aktuellen Kategorie startet
            $params['activeItem']->setValue('category_id', $params['id']);
        }

        $result = '
            <tr id="' . self::CONTAINER . '" class="collapse mark">
                <td colspan="2"></td>
                <td colspan="5">
                    <div class="rex-collapse-content">
                    ' . parent::renderFormAndSave(self::PREFIX, $params) . '
                    </div>
                </td>
            </tr>';

        // Bei CAT_ADDED und CAT_UPDATED nur speichern und kein Formular zurückgeben
        if ('CAT_UPDATED' == $ep->getName() || 'CAT_ADDED' == $ep->getName()) {
            return $ep->getSubject();
        }
        return $ep->getSubject() . $result;
    }
}

$categoryHandler = new CategoryHandler();

Extension::register('CAT_FORM_ADD', $categoryHandler->extendForm(...));
Extension::register('CAT_FORM_EDIT', $categoryHandler->extendForm(...));

Extension::register('CAT_ADDED', $categoryHandler->extendForm(...), Extension::EARLY);
Extension::register('CAT_UPDATED', $categoryHandler->extendForm(...), Extension::EARLY);

Extension::register('CAT_FORM_BUTTONS', $categoryHandler->renderToggleButton(...));
