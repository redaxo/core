<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Translation\I18n;

final class MediaCategoryHandler
{
    private function __construct() {}

    /**
     * @param string $name The name of the new category
     * @param MediaCategory|null $parent The category in which the new category should be created, or null for a top/root level category
     *
     * @return string A success message
     */
    public static function addCategory(string $name, ?MediaCategory $parent): string
    {
        $db = Sql::factory();

        // root category
        $parentId = 0;
        $path = '|';
        if ($parent) {
            $parentId = $parent->id;
            $path .= implode('|', $parent->path) . '|' . $parent->id . '|';
        }

        $db->setTable(Core::getTablePrefix() . 'media_category');
        $db->setValue('name', $name);
        $db->setValue('parent_id', $parentId);
        $db->setValue('path', $path);
        $db->addGlobalCreateFields();
        $db->addGlobalUpdateFields();

        $db->insert();

        MediaPoolCache::deleteCategoryList($parentId);

        Extension::dispatch(new ExtensionPoint('MEDIA_CATEGORY_ADDED', [
            'id' => $db->getLastId(),
            'parent_id' => $parentId,
            'name' => $name,
        ]));

        return I18n::msg('pool_kat_saved', $name);
    }

    /**
     * @throws UserMessageException
     * @return string A success message
     */
    public static function deleteCategory(int $categoryId): string
    {
        $gf = Sql::factory();
        $gf->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media WHERE category_id=?', [$categoryId]);
        $gd = Sql::factory();
        $gd->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'media_category WHERE parent_id=?', [$categoryId]);
        if (0 == $gf->getRows() && 0 == $gd->getRows()) {
            if ($uses = self::categoryIsInUse($categoryId)) {
                $gf->setQuery('SELECT name FROM ' . Core::getTable('media_category') . ' WHERE id=?', [$categoryId]);
                $name = "{$gf->getValue('name')} [$categoryId]";
                throw new UserMessageException('<strong>' . I18n::msg('pool_kat_delete_error', $name) . ' ' . I18n::msg('pool_object_in_use_by') . '</strong><br />' . $uses);
            }

            $gf->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'media_category WHERE id=?', [$categoryId]);
            MediaPoolCache::deleteCategory($categoryId);
            MediaPoolCache::deleteLists();
        } else {
            throw new UserMessageException(I18n::msg('pool_kat_not_deleted'));
        }

        Extension::dispatch(new ExtensionPoint('MEDIA_CATEGORY_DELETED', ['id' => $categoryId]));

        return I18n::msg('pool_kat_deleted');
    }

    /** @return false|string false or warning message */
    public static function categoryIsInUse(int $categoryId): string|false
    {
        /** @var list<string> $warning */
        $warning = [];
        $warning = Extension::dispatch(new ExtensionPoint('MEDIA_CATEGORY_IS_IN_USE', $warning, [
            'id' => $categoryId,
        ]));

        if ($warning) {
            return implode('<br />', $warning);
        }

        return false;
    }

    /**
     * @param int $categoryId The id of the category to edit
     * @param array{name: string} $data The category data
     *
     * @return string A success message
     */
    public static function editCategory(int $categoryId, array $data): string
    {
        $catName = $data['name'];

        $db = Sql::factory();
        $db->setTable(Core::getTablePrefix() . 'media_category');
        $db->setWhere(['id' => $categoryId]);
        $db->setValue('name', $catName);
        $db->addGlobalUpdateFields();

        $db->update();

        MediaPoolCache::deleteCategory($categoryId);

        Extension::dispatch(new ExtensionPoint('MEDIA_CATEGORY_UPDATED', [
            'id' => $categoryId,
            'name' => $catName,
        ]));

        return I18n::msg('pool_kat_updated', $catName);
    }
}
