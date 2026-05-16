<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Core;
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
        Media::clearInstance($filename);
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

    /** Löscht die gecachte Liste mit den Media der Kategorie. */
    public static function deleteList(int $categoryId): void
    {
        File::delete(Path::coreCache('mediapool/' . $categoryId . '.mlist'));
        MediaCategory::clearInstanceList([$categoryId, 'media']);
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

    /** Löscht die gecachte Media-Kategorien-Liste. */
    public static function deleteCategoryList(int $categoryId): void
    {
        File::delete(Path::coreCache('mediapool/' . $categoryId . '.mclist'));
        MediaCategory::clearInstanceList([$categoryId, 'children']);
    }

    /**
     * Generiert den Cache des Mediums.
     *
     * @param string $filename Dateiname des zu generierenden Mediums
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function generate(string $filename): bool
    {
        $query = 'SELECT * FROM ' . Core::getTable('media') . ' WHERE filename = ?';
        $sql = Sql::factory();
        // $sql->setDebug();
        $sql->setQuery($query, [$filename]);

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

        $query = 'SELECT * FROM ' . Core::getTable('media_category') . ' WHERE id = ?';
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
     * Generiert eine Liste mit den Media einer Kategorie.
     *
     * @param int $categoryId Id der Kategorie
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function generateList(int $categoryId): bool
    {
        // sanity check
        if ($categoryId < 0) {
            return false;
        }

        $query = 'SELECT filename FROM ' . Core::getTable('media') . ' WHERE category_id = ?';
        $sql = Sql::factory();
        $sql->setQuery($query, [$categoryId]);

        $cacheArray = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $cacheArray[] = $sql->getValue('filename');
            $sql->next();
        }

        $listFile = Path::coreCache('mediapool/' . $categoryId . '.mlist');
        return File::putCache($listFile, $cacheArray);
    }

    /**
     * Generiert eine Liste mit den Kindkategorien einer Kategorie.
     *
     * @param int $categoryId Id der Kategorie
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function generateCategoryList(int $categoryId): bool
    {
        // sanity check
        if ($categoryId < 0) {
            return false;
        }

        $query = 'SELECT id, cast( name AS SIGNED ) AS sort FROM ' . Core::getTable('media_category') . ' WHERE parent_id = ? ORDER BY sort, name';
        $sql = Sql::factory();
        // $sql->setDebug();
        $sql->setQuery($query, [$categoryId]);

        $cacheArray = [];
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $cacheArray[] = $sql->getValue('id');
            $sql->next();
        }

        $listFile = Path::coreCache('mediapool/' . $categoryId . '.mclist');
        return File::putCache($listFile, $cacheArray);
    }
}
