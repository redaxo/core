<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MetaInfo\Form\DefaultType;
use Redaxo\Core\MetaInfo\MetaInfo;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function in_array;

/**
 * @internal
 */
class MediaHandler extends AbstractHandler
{
    public const PREFIX = 'med_';

    /**
     * Extension to check whether the given media is still in use.
     *
     * @return list<string>
     */
    public static function isMediaInUse(ExtensionPoint $ep)
    {
        $params = $ep->getParams();
        $warning = $ep->subject;

        $sql = Sql::factory();
        $sql->setQuery('SELECT `name`, `type_id` FROM `' . Core::getTablePrefix() . 'metainfo_field` WHERE `type_id` IN(6,7)');

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
            $prefix = MetaInfo::metaPrefix($name);
            if (self::PREFIX === $prefix) {
                $key = 'media';
            } elseif (LanguageHandler::PREFIX === $prefix) {
                $key = 'clangs';
            } elseif (CategoryHandler::PREFIX === $prefix) {
                $key = 'categories';
            } else {
                $key = 'articles';
            }
            $where[$key][] = match ((int) $sql->getValue('type_id')) {
                DefaultType::REX_MEDIA_WIDGET => 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier($name) . ')',
                default => throw new LogicException('Unexpected fieldtype "' . $sql->getValue('type_id') . '".'),
            };
            $sql->next();
        }

        $articles = '';
        if (!empty($where['articles'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . Core::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['articles']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $parentId = (int) $artArr['parent_id'];
                $articles .= '<li><a href="javascript:openPage(\'' . Url::backendPage('content', ['article_id' => $aid, 'mode' => 'meta', 'clang' => $clang]) . '\')">' . (string) $artArr['name'] . '</a></li>';
            }
            if ('' != $articles) {
                $warning[] = I18n::msg('minfo_media_in_use_art') . '<br /><ul>' . $articles . '</ul>';
            }
        }

        $categories = '';
        if (!empty($where['categories'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . Core::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['categories']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $parentId = (int) $artArr['parent_id'];
                $categories .= '<li><a href="javascript:openPage(\'' . Url::backendPage('structure', ['edit_id' => $aid, 'function' => 'edit_cat', 'category_id' => $parentId, 'clang' => $clang]) . '\')">' . (string) $artArr['catname'] . '</a></li>';
            }
            if ('' != $categories) {
                $warning[] = I18n::msg('minfo_media_in_use_cat') . '<br /><ul>' . $categories . '</ul>';
            }
        }

        $media = '';
        if (!empty($where['media'])) {
            $items = $sql->getArray('SELECT id, filename, category_id FROM ' . Core::getTablePrefix() . 'media WHERE ' . implode(' OR ', $where['media']));
            foreach ($items as $medArr) {
                $id = (int) $medArr['id'];
                $filename = (string) $medArr['filename'];
                $catId = (int) $medArr['category_id'];
                $media .= '<li><a href="' . Url::backendPage('mediapool/detail', ['file_id' => $id, 'rex_file_category' => $catId]) . '">' . $filename . '</a></li>';
            }
            if ('' != $media) {
                $warning[] = I18n::msg('minfo_media_in_use_med') . '<br /><ul>' . $media . '</ul>';
            }
        }

        $clangs = '';
        if (!empty($where['clangs'])) {
            $items = $sql->getArray('SELECT id, name FROM ' . Core::getTablePrefix() . 'clang WHERE ' . implode(' OR ', $where['clangs']));
            foreach ($items as $clangArr) {
                $name = (string) $clangArr['name'];
                if (Core::getUser()?->admin) {
                    $clangs .= '<li><a href="javascript:openPage(\'' . Url::backendPage('system/lang', ['clang_id' => $clangArr['id'], 'func' => 'editclang']) . '\')">' . $name . '</a></li>';
                } else {
                    $clangs .= '<li>' . $name . '</li>';
                }
            }
            if ('' != $clangs) {
                $warning[] = I18n::msg('minfo_media_in_use_clang') . '<br /><ul>' . $clangs . '</ul>';
            }
        }

        return $warning;
    }

    /** @return string */
    protected function buildFilterCondition(array $params)
    {
        $restrictionsCondition = '';

        $catId = Request::session('media[rex_file_category]', 'int');
        if (isset($params['activeItem'])) {
            $catId = $params['activeItem']->getValue('category_id');
        }

        if ('' !== $catId) {
            $s = '';
            if (0 != $catId) {
                $OOCat = Type::notNull(MediaCategory::get($catId));

                // Alle Metafelder des Pfades sind erlaubt
                foreach ($OOCat->path as $pathElement) {
                    if ('' != $pathElement) {
                        $s .= ' OR FIND_IN_SET(' . $pathElement . ', `p`.`restrictions`)';
                    }
                }
            }

            // Auch die Kategorie selbst kann Metafelder haben
            $s .= ' OR FIND_IN_SET(' . $catId . ', `p`.`restrictions`)';

            $restrictionsCondition = 'AND (`p`.`restrictions` = "" OR `p`.`restrictions` IS NULL ' . $s . ')';
        }

        return $restrictionsCondition;
    }

    /** @return array */
    protected function handleSave(array $params, Sql $sqlFields)
    {
        if ('post' != Request::requestMethod() || !isset($params['id'])) {
            return $params;
        }

        $media = Sql::factory();
        //  $media->setDebug();
        $media->setTable(Core::getTablePrefix() . 'media');
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

    public function extendForm(ExtensionPoint $ep)
    {
        $params = $ep->getParams();
        $params['save'] = in_array($ep->name, ['MEDIA_ADDED', 'MEDIA_UPDATED'], true);

        // Nur beim EDIT gibts auch ein Medium zum bearbeiten
        if ('MEDIA_FORM_EDIT' == $ep->name) {
            $params['activeItem'] = $params['media'];
            unset($params['media']);
        } elseif ('MEDIA_ADDED' == $ep->name) {
            $sql = Sql::factory();

            $qry = 'SELECT id FROM ' . Core::getTablePrefix() . 'media WHERE filename=:filename';
            $sql->setQuery($qry, ['filename' => $params['filename']]);
            if (1 == $sql->getRows()) {
                $params['id'] = (int) $sql->getValue('id');
            } else {
                throw new RuntimeException('Error occured during file upload.');
            }
        }

        return $ep->subject . parent::renderFormAndSave(self::PREFIX, $params);
    }
}

$mediaHandler = new MediaHandler();

Extension::register('MEDIA_FORM_EDIT', $mediaHandler->extendForm(...));
Extension::register('MEDIA_FORM_ADD', $mediaHandler->extendForm(...));

Extension::register('MEDIA_ADDED', $mediaHandler->extendForm(...), ExtensionLevel::Early);
Extension::register('MEDIA_UPDATED', $mediaHandler->extendForm(...), ExtensionLevel::Early);

Extension::register('MEDIA_IS_IN_USE', MediaHandler::isMediaInUse(...));
