<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 */
class rex_metainfo_category_handler extends rex_metainfo_handler
{
    public const PREFIX = 'cat_';
    public const CONTAINER = 'rex-structure-category-metainfo';

    /** @return string */
    public function renderToggleButton(rex_extension_point $ep)
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
    public function handleSave(array $params, rex_sql $sqlFields)
    {
        // Only save when the category itself is saved, as only that request is protected by a csrf token.
        if (!$params['save'] || 'post' != rex_request_method()) {
            return $params;
        }

        $article = rex_sql::factory();
        // $article->setDebug();
        $article->setTable(rex::getTablePrefix() . 'article');
        $article->setWhere('id=:id AND clang_id=:clang', ['id' => $params['id'], 'clang' => $params['clang']]);

        parent::fetchRequestValues($params, $article, $sqlFields);

        // do the save only when metafields are defined
        if ($article->hasValues()) {
            $article->update();
        }

        // Artikel nochmal mit den zusätzlichen Werten neu generieren
        rex_article_cache::generateMeta($params['id'], $params['clang']);

        return $params;
    }

    /** @return string */
    protected function buildFilterCondition(array $params)
    {
        $s = '';

        if (!empty($params['id'])) {
            $OOCat = rex_category::get($params['id'], $params['clang']);

            // Alle Metafelder des Pfades sind erlaubt
            foreach ($OOCat->getPathAsArray() as $pathElement) {
                if ('' != $pathElement) {
                    $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                }
            }

            // Auch die Kategorie selbst kann Metafelder haben
            $s .= ' OR `p`.`restrictions` LIKE "%|' . $params['id'] . '|%"';
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

    public function extendForm(rex_extension_point $ep)
    {
        $params = $ep->getParams();
        $params['save'] = in_array($ep->getName(), ['CAT_ADDED', 'CAT_UPDATED'], true);

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

$catHandler = new rex_metainfo_category_handler();

rex_extension::register('CAT_FORM_ADD', $catHandler->extendForm(...));
rex_extension::register('CAT_FORM_EDIT', $catHandler->extendForm(...));

rex_extension::register('CAT_ADDED', $catHandler->extendForm(...), rex_extension::EARLY);
rex_extension::register('CAT_UPDATED', $catHandler->extendForm(...), rex_extension::EARLY);

rex_extension::register('CAT_FORM_BUTTONS', $catHandler->renderToggleButton(...));
