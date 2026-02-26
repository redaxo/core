<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 */
class rex_metainfo_media_handler extends rex_metainfo_handler
{
    public const PREFIX = 'med_';

    /**
     * Extension to check whether the given media is still in use.
     *
     * @throws rex_exception
     *
     * @return list<string>
     */
    public static function isMediaInUse(rex_extension_point $ep)
    {
        $params = $ep->getParams();
        $warning = $ep->getSubject();

        $sql = rex_sql::factory();
        $sql->setQuery('SELECT `name`, `type_id` FROM `' . rex::getTablePrefix() . 'metainfo_field` WHERE `type_id` IN(6,7)');

        $rows = $sql->getRows();
        if (0 == $rows) {
            return $warning;
        }

        $where = [
            'articles' => [],
            'media' => [],
            'clangs' => [],
        ];
        $escapedFilename = $sql->escape($params['filename']);
        for ($i = 0; $i < $rows; ++$i) {
            $name = (string) $sql->getValue('name');
            $prefix = rex_metainfo_meta_prefix($name);
            if (self::PREFIX === $prefix) {
                $key = 'media';
            } elseif (rex_metainfo_clang_handler::PREFIX === $prefix) {
                $key = 'clangs';
            } elseif (rex_metainfo_category_handler::PREFIX === $prefix) {
                $key = 'categories';
            } else {
                $key = 'articles';
            }
            $where[$key][] = match ((int) $sql->getValue('type_id')) {
                rex_metainfo_default_type::REX_MEDIA_WIDGET => $sql->escapeIdentifier($name) . ' = ' . $escapedFilename,
                rex_metainfo_default_type::REX_MEDIALIST_WIDGET => 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier($name) . ')',
                default => throw new rex_exception('Unexpected fieldtype "' . $sql->getValue('type_id') . '"!'),
            };
            $sql->next();
        }

        $articles = '';
        if (!empty($where['articles'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . rex::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['articles']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $parentId = (int) $artArr['parent_id'];
                $articles .= '<li><a href="javascript:openPage(\'' . rex_url::backendPage('content', ['article_id' => $aid, 'mode' => 'meta', 'clang' => $clang]) . '\')">' . (string) $artArr['name'] . '</a></li>';
            }
            if ('' != $articles) {
                $warning[] = rex_i18n::msg('minfo_media_in_use_art') . '<br /><ul>' . $articles . '</ul>';
            }
        }

        $categories = '';
        if (!empty($where['categories'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . rex::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['categories']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $parentId = (int) $artArr['parent_id'];
                $categories .= '<li><a href="javascript:openPage(\'' . rex_url::backendPage('structure', ['edit_id' => $aid, 'function' => 'edit_cat', 'category_id' => $parentId, 'clang' => $clang]) . '\')">' . (string) $artArr['catname'] . '</a></li>';
            }
            if ('' != $categories) {
                $warning[] = rex_i18n::msg('minfo_media_in_use_cat') . '<br /><ul>' . $categories . '</ul>';
            }
        }

        $media = '';
        if (!empty($where['media'])) {
            $items = $sql->getArray('SELECT id, filename, category_id FROM ' . rex::getTablePrefix() . 'media WHERE ' . implode(' OR ', $where['media']));
            foreach ($items as $medArr) {
                $id = (int) $medArr['id'];
                $filename = (string) $medArr['filename'];
                $catId = (int) $medArr['category_id'];
                $media .= '<li><a href="' . rex_url::backendPage('mediapool/detail', ['file_id' => $id, 'rex_file_category' => $catId]) . '">' . $filename . '</a></li>';
            }
            if ('' != $media) {
                $warning[] = rex_i18n::msg('minfo_media_in_use_med') . '<br /><ul>' . $media . '</ul>';
            }
        }

        $clangs = '';
        if (!empty($where['clangs'])) {
            $items = $sql->getArray('SELECT id, name FROM ' . rex::getTablePrefix() . 'clang WHERE ' . implode(' OR ', $where['clangs']));
            foreach ($items as $clangArr) {
                $name = (string) $clangArr['name'];
                if (rex::getUser()?->isAdmin()) {
                    $clangs .= '<li><a href="javascript:openPage(\'' . rex_url::backendPage('system/lang', ['clang_id' => $clangArr['id'], 'func' => 'editclang']) . '\')">' . $name . '</a></li>';
                } else {
                    $clangs .= '<li>' . $name . '</li>';
                }
            }
            if ('' != $clangs) {
                $warning[] = rex_i18n::msg('minfo_media_in_use_clang') . '<br /><ul>' . $clangs . '</ul>';
            }
        }

        return $warning;
    }

    /** @return string */
    protected function buildFilterCondition(array $params)
    {
        $restrictionsCondition = '';

        $catId = rex_session('media[rex_file_category]', 'int');
        if (isset($params['activeItem'])) {
            $catId = $params['activeItem']->getValue('category_id');
        }

        if ('' !== $catId) {
            $s = '';
            if (0 != $catId) {
                $OOCat = rex_media_category::get($catId);

                // Alle Metafelder des Pfades sind erlaubt
                foreach ($OOCat->getPathAsArray() as $pathElement) {
                    if ('' != $pathElement) {
                        $s .= ' OR `p`.`restrictions` LIKE "%|' . $pathElement . '|%"';
                    }
                }
            }

            // Auch die Kategorie selbst kann Metafelder haben
            $s .= ' OR `p`.`restrictions` LIKE "%|' . $catId . '|%"';

            $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';
        }

        return $restrictionsCondition;
    }

    /** @return array */
    protected function handleSave(array $params, rex_sql $sqlFields)
    {
        if ('post' != rex_request_method() || !isset($params['id'])) {
            return $params;
        }

        $media = rex_sql::factory();
        //  $media->setDebug();
        $media->setTable(rex::getTablePrefix() . 'media');
        $media->setWhere('id=:mediaid', ['mediaid' => $params['id']]);

        parent::fetchRequestValues($params, $media, $sqlFields);

        // do the save only when EP = MEDIA_ADDED/UPDATED and metafields are defined
        if ($params['save'] && $media->hasValues()) {
            $media->update();
        }

        return $params;
    }

    protected function renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $inputType)
    {
        return $field;
    }

    public function extendForm(rex_extension_point $ep)
    {
        $params = $ep->getParams();
        $params['save'] = $save = in_array($ep->getName(), ['MEDIA_ADDED', 'MEDIA_UPDATED'], true);

        // Nur beim EDIT gibts auch ein Medium zum bearbeiten
        if ('MEDIA_FORM_EDIT' == $ep->getName()) {
            $params['activeItem'] = $params['media'];
            unset($params['media']);
        } elseif ('MEDIA_ADDED' == $ep->getName()) {
            $sql = rex_sql::factory();

            $qry = 'SELECT id FROM ' . rex::getTablePrefix() . 'media WHERE filename=:filename';
            $sql->setQuery($qry, ['filename' => $params['filename']]);
            if (1 == $sql->getRows()) {
                $params['id'] = (int) $sql->getValue('id');
            } else {
                throw new rex_exception('Error occured during file upload!');
            }
        }

        return $ep->getSubject() . parent::renderFormAndSave(self::PREFIX, $params, $save);
    }
}

$mediaHandler = new rex_metainfo_media_handler();

rex_extension::register('MEDIA_FORM_EDIT', $mediaHandler->extendForm(...));
rex_extension::register('MEDIA_FORM_ADD', $mediaHandler->extendForm(...));

rex_extension::register('MEDIA_ADDED', $mediaHandler->extendForm(...), rex_extension::EARLY);
rex_extension::register('MEDIA_UPDATED', $mediaHandler->extendForm(...), rex_extension::EARLY);

rex_extension::register('MEDIA_IS_IN_USE', rex_metainfo_media_handler::isMediaInUse(...));
