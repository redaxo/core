<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

final class ArticleCache
{
    private function __construct() {}

    /**
     * Löscht die gecachten Dateien eines Artikels. Wenn keine clang angegeben, wird
     * der Artikel-Cache in allen Sprachen gelöscht.
     *
     * @param int $id ArtikelId des Artikels
     * @param int $clangId ClangId des Artikels
     *
     * @return bool True on success, False on errro
     */
    public static function delete($id, $clangId = null)
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $clangId && $clangId != $otherClangId) {
                continue;
            }

            self::deleteMeta($id, $clangId);
            self::deleteContent($id, $clangId);
        }
        self::deleteLists($id);

        return true;
    }

    /**
     * Löscht die gecachten Meta-Dateien eines Artikels. Wenn keine clang angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     *
     * @param int $id ArtikelId des Artikels
     * @param int $clangId ClangId des Artikels
     *
     * @return bool True on success, False on errro
     */
    public static function deleteMeta($id, $clangId = null)
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $clangId && $clangId != $otherClangId) {
                continue;
            }

            File::delete($cachePath . $id . '.' . $otherClangId . '.article');
            Article::clearInstance([$id, $otherClangId]);
            Category::clearInstance([$id, $otherClangId]);
        }

        return true;
    }

    /**
     * Löscht die gecachten Content-Dateien eines Artikels. Wenn keine clang angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     *
     * @param int $id ArtikelId des Artikels
     * @param int $clangId ClangId des Artikels
     *
     * @return bool True on success, False on errro
     */
    public static function deleteContent($id, $clangId = null)
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $clangId && $clangId != $otherClangId) {
                continue;
            }

            File::delete($cachePath . $id . '.' . $otherClangId . '.content');
        }

        return true;
    }

    /**
     * Löscht die gecachten List-Dateien eines Artikels. Wenn keine clang angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     *
     * @param int $id ArtikelId des Artikels
     *
     * @return bool True on success, False on errro
     */
    public static function deleteLists($id)
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (['alist', 'clist'] as $list) {
            File::delete($cachePath . $id . '.' . $list);
            StructureElement::clearInstanceList([$id, $list]);
        }

        return true;
    }

    /**
     * Generiert den Artikel-Cache der Metainformationen.
     *
     * @param int $articleId Id des zu generierenden Artikels
     * @param int $clangId ClangId des Artikels
     *
     * @return bool|string TRUE bei Erfolg, FALSE wenn eine ungütlige article_id übergeben wird, sonst eine Fehlermeldung
     */
    public static function generateMeta($articleId, $clangId = null)
    {
        // sanity check
        if ($articleId <= 0) {
            return false;
        }

        $qry = 'SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE id=' . (int) $articleId;
        if (null !== $clangId) {
            $qry .= ' AND clang_id=' . (int) $clangId;
        }

        $sql = Sql::factory();
        $sql->setQuery($qry);
        $fieldnames = $sql->getFieldnames();
        foreach ($sql as $row) {
            $clang = $row->getValue('clang_id');

            // --------------------------------------------------- Artikelparameter speichern
            $params = [];
            foreach ($fieldnames as $field) {
                $params[$field] = match ($field) {
                    'createdate', 'updatedate' => $row->getDateTimeValue($field),
                    default => $row->getValue($field),
                };
            }

            $articleFile = Path::coreCache('structure/' . $articleId . '.' . $clang . '.article');
            if (!File::putCache($articleFile, $params)) {
                return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
            }
        }

        return true;
    }

    /**
     * Generiert alle *.alist u. *.clist Dateien einer Kategorie/eines Artikels.
     *
     * @param int $parentId KategorieId oder ArtikelId, die erneuert werden soll
     *
     * @return bool|string TRUE wenn der Artikel gelöscht wurde, sonst eine Fehlermeldung
     */
    public static function generateLists($parentId)
    {
        // sanity check
        if ($parentId < 0) {
            return false;
        }

        // --------------------------------------- ARTICLE LIST

        $GC = Sql::factory();
        // $GC->setDebug();
        $GC->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=:clang AND ((parent_id=:id and startarticle=0) OR (id=:id and startarticle=1)) order by priority,name', ['id' => $parentId, 'clang' => Language::getStartId()]);

        $cacheArray = [];
        foreach ($GC as $row) {
            $cacheArray[] = (int) $row->getValue('id');
        }

        $articleListFile = Path::coreCache('structure/' . $parentId . '.alist');
        if (!File::putCache($articleListFile, $cacheArray)) {
            return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
        }

        // --------------------------------------- CAT LIST

        $GC = Sql::factory();
        $GC->setQuery('select * from ' . Core::getTablePrefix() . 'article where parent_id=:id and clang_id=:clang and startarticle=1 order by catpriority,name', ['id' => $parentId, 'clang' => Language::getStartId()]);

        $cacheArray = [];
        foreach ($GC as $row) {
            $cacheArray[] = (int) $row->getValue('id');
        }

        $articleCategoriesFile = Path::coreCache('structure/' . $parentId . '.clist');
        if (!File::putCache($articleCategoriesFile, $cacheArray)) {
            return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
        }

        return true;
    }
}
