<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

use function is_array;

use const GLOB_NOSORT;

final class MediaPoolCache
{
    private function __construct() {}

    /** Löscht die gecachte Medium-Datei. */
    public static function delete(string $filename): void
    {
        File::delete(Path::coreCache('mediapool/' . $filename . '.media'));
        Media::clearInstances($filename);
        self::deleteLists();
    }

    /** Löscht die gecachten Dateien der Media-Kategorie. */
    public static function deleteCategory(int $categoryId): void
    {
        File::delete(Path::coreCache('mediapool/' . $categoryId . '.mcat'));
        MediaCategory::clearInstance($categoryId);
        self::deleteCategoryLists();
    }

    /** Löscht die gecachten Media-Listen. */
    public static function deleteLists(): void
    {
        $cachePath = Path::coreCache('mediapool/');

        $glob = glob($cachePath . '*.mlist', GLOB_NOSORT);
        if (is_array($glob)) {
            foreach ($glob as $file) {
                File::delete($file);
            }
        }
        MediaCategory::clearInstanceListPool();
    }

    /**
     * Deletes the cached media list of a category.
     *
     * @param int|null $categoryId `null` for the root level
     */
    public static function deleteList(?int $categoryId): void
    {
        File::delete(Path::coreCache('mediapool/' . ($categoryId ?? 0) . '.mlist'));
        MediaCategory::clearInstanceList([$categoryId ?? 0, 'media']);
    }

    /** Löscht die gecachten Media-Kategorien-Listen. */
    public static function deleteCategoryLists(): void
    {
        $cachePath = Path::coreCache('mediapool/');

        $glob = glob($cachePath . '*.mclist', GLOB_NOSORT);
        if (is_array($glob)) {
            foreach ($glob as $file) {
                File::delete($file);
            }
        }
        MediaCategory::clearInstanceListPool();
    }

    /**
     * Deletes the cached subcategory list of a category.
     *
     * @param int|null $categoryId `null` for the root level
     */
    public static function deleteCategoryList(?int $categoryId): void
    {
        File::delete(Path::coreCache('mediapool/' . ($categoryId ?? 0) . '.mclist'));
        MediaCategory::clearInstanceList([$categoryId ?? 0, 'children']);
    }

    /**
     * Generates the cache of a medium: the shared columns plus the rows of the translation table keyed by language.
     *
     * @return bool `true` on success, otherwise `false`
     */
    public static function generate(string $filename): bool
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM rex_media WHERE filename = ?', [$filename]);

        if (0 == $sql->getRows()) {
            return false;
        }

        $cacheArray = [];
        foreach ($sql->getFieldNames() as $fieldName) {
            $cacheArray[$fieldName] = match ($fieldName) {
                'createdate', 'updatedate' => $sql->getDateTimeValue($fieldName),
                default => $sql->getValue($fieldName),
            };
        }

        $cacheArray['translations'] = [];
        $translations = Sql::factory();
        $translations->setQuery('SELECT * FROM rex_media_translation WHERE media_id = ?', [$cacheArray['id']]);
        foreach ($translations as $translation) {
            $languageId = (int) $translation->getValue('language_id');
            foreach ($translation->getFieldNames() as $fieldName) {
                if ('media_id' === $fieldName || 'language_id' === $fieldName) {
                    continue;
                }
                $cacheArray['translations'][$languageId][$fieldName] = $translation->getValue($fieldName);
            }
        }

        $mediaFile = Path::coreCache('mediapool/' . $filename . '.media');
        return File::putCache($mediaFile, $cacheArray);
    }

    /**
     * Generiert den Cache der Media-Kategorie.
     *
     * @param int $categoryId Id des zu generierenden Media-Kategorie
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function generateCategory(int $categoryId): bool
    {
        // sanity check
        if ($categoryId < 0) {
            return false;
        }

        $query = 'SELECT * FROM rex_media_category WHERE id = ?';
        $sql = Sql::factory();
        // $sql->setDebug();
        $sql->setQuery($query, [$categoryId]);

        if (0 == $sql->getRows()) {
            return false;
        }

        $cacheArray = [];
        foreach ($sql->getFieldNames() as $fieldName) {
            $cacheArray[$fieldName] = match ($fieldName) {
                'createdate', 'updatedate' => $sql->getDateTimeValue($fieldName),
                default => $sql->getValue($fieldName),
            };
        }

        $catFile = Path::coreCache('mediapool/' . $categoryId . '.mcat');
        return File::putCache($catFile, $cacheArray);
    }

    /**
     * Generates the cached media list of a category.
     *
     * @param int|null $categoryId Id of the category, `null` for the root level
     *
     * @return bool `true` on success, otherwise `false`
     */
    public static function generateList(?int $categoryId): bool
    {
        if (null !== $categoryId && $categoryId < 1) {
            return false;
        }

        $query = 'SELECT filename FROM rex_media WHERE category_id <=> ?';
        $sql = Sql::factory();
        $sql->setQuery($query, [$categoryId]);

        $cacheArray = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $cacheArray[] = $sql->getValue('filename');
            $sql->next();
        }

        $listFile = Path::coreCache('mediapool/' . ($categoryId ?? 0) . '.mlist');
        return File::putCache($listFile, $cacheArray);
    }

    /**
     * Generates the cached subcategory list of a category.
     *
     * @param int|null $categoryId Id of the category, `null` for the root level
     *
     * @return bool `true` on success, otherwise `false`
     */
    public static function generateCategoryList(?int $categoryId): bool
    {
        if (null !== $categoryId && $categoryId < 1) {
            return false;
        }

        $query = 'SELECT id, cast( name AS SIGNED ) AS sort FROM rex_media_category WHERE parent_id <=> ? ORDER BY sort, name';
        $sql = Sql::factory();
        // $sql->setDebug();
        $sql->setQuery($query, [$categoryId]);

        $cacheArray = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $cacheArray[] = $sql->getValue('id');
            $sql->next();
        }

        $listFile = Path::coreCache('mediapool/' . ($categoryId ?? 0) . '.mclist');
        return File::putCache($listFile, $cacheArray);
    }
}
