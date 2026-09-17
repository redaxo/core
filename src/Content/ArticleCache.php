<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

final class ArticleCache
{
    private function __construct() {}

    /**
     * Löscht die gecachten Dateien eines Artikels. Wenn keine Sprache angegeben, wird
     * der Artikel-Cache in allen Sprachen gelöscht.
     */
    public static function delete(int $id, ?int $languageId = null): bool
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $languageId && $languageId != $otherClangId) {
                continue;
            }

            self::deleteMeta($id, $languageId);
            self::deleteContent($id, $languageId);
        }
        self::deleteLists($id);

        return true;
    }

    /**
     * Löscht die gecachten Meta-Dateien eines Artikels. Wenn keine Sprache angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     */
    public static function deleteMeta(int $id, ?int $languageId = null): bool
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $languageId && $languageId != $otherClangId) {
                continue;
            }

            File::delete($cachePath . $id . '.' . $otherClangId . '.article');
            Article::clearInstance([$id, $otherClangId]);
            Category::clearInstance([$id, $otherClangId]);
        }

        return true;
    }

    /**
     * Löscht die gecachten Content-Dateien eines Artikels. Wenn keine Sprache angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     */
    public static function deleteContent(int $id, ?int $languageId = null): bool
    {
        // sanity check
        if ($id < 0) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (Language::getAllIds() as $otherClangId) {
            if (null !== $languageId && $languageId != $otherClangId) {
                continue;
            }

            File::delete($cachePath . $id . '.' . $otherClangId . '.content');
        }

        return true;
    }

    /**
     * Löscht die gecachten List-Dateien eines Artikels. Wenn keine Sprache angegeben, wird
     * der Artikel in allen Sprachen gelöscht.
     */
    public static function deleteLists(?int $id): bool
    {
        if (null !== $id && $id < 1) {
            return false;
        }

        $cachePath = Path::coreCache('structure/');

        foreach (['alist', 'clist'] as $list) {
            File::delete($cachePath . ($id ?? 0) . '.' . $list);
            StructureElement::clearInstanceList([$id ?? 0, $list]);
        }

        return true;
    }

    /**
     * Generiert den Artikel-Cache der Metainformationen.
     *
     * @return bool|string TRUE bei Erfolg, FALSE wenn eine ungütlige article_id übergeben wird, sonst eine Fehlermeldung
     */
    public static function generateMeta(int $articleId, ?int $languageId = null): bool|string
    {
        // sanity check
        if ($articleId <= 0) {
            return false;
        }

        // one cache file per language, holding the shared columns together with the translation
        $qry = 'SELECT a.*, t.* FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id WHERE a.id = ?';
        $params = [$articleId];
        if (null !== $languageId) {
            $qry .= ' AND t.language_id = ?';
            $params[] = $languageId;
        }

        $sql = Sql::factory();
        $sql->setQuery($qry, $params);
        $fieldnames = $sql->getFieldnames();
        foreach ($sql as $row) {
            $rowLanguageId = $row->getValue('language_id');

            // --------------------------------------------------- Artikelparameter speichern
            $params = [];
            foreach ($fieldnames as $field) {
                if ('article_id' === $field) {
                    continue;
                }
                $params[$field] = match ($field) {
                    'createdate', 'updatedate' => $row->getDateTimeValue($field),
                    default => $row->getValue($field),
                };
            }

            $articleFile = Path::coreCache('structure/' . $articleId . '.' . $rowLanguageId . '.article');
            if (!File::putCache($articleFile, $params)) {
                return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
            }
        }

        return true;
    }

    /**
     * Generates the article list (`*.alist`) and category list (`*.clist`) files of a category.
     *
     * @param int|null $parentId Id of the category, `null` for the root level
     *
     * @return bool|string `true` on success, otherwise an error message
     */
    public static function generateLists(?int $parentId): bool|string
    {
        if (null !== $parentId && $parentId < 1) {
            return false;
        }

        // --------------------------------------- ARTICLE LIST

        $GC = Sql::factory();
        // $GC->setDebug();
        // the lists are shared by all languages, the names of the start language break priority ties
        $GC->setQuery('SELECT a.id FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = :language WHERE (a.parent_id <=> :id AND a.startarticle = 0) OR (a.id = :id AND a.startarticle = 1) ORDER BY a.priority, t.name', ['id' => $parentId, 'language' => Language::getStartId()]);

        $cacheArray = [];
        foreach ($GC as $row) {
            $cacheArray[] = (int) $row->getValue('id');
        }

        $articleListFile = Path::coreCache('structure/' . ($parentId ?? 0) . '.alist');
        if (!File::putCache($articleListFile, $cacheArray)) {
            return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
        }

        // --------------------------------------- CAT LIST

        $GC = Sql::factory();
        $GC->setQuery('SELECT a.id FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = :language WHERE a.parent_id <=> :id AND a.startarticle = 1 ORDER BY a.catpriority, t.catname', ['id' => $parentId, 'language' => Language::getStartId()]);

        $cacheArray = [];
        foreach ($GC as $row) {
            $cacheArray[] = (int) $row->getValue('id');
        }

        $articleCategoriesFile = Path::coreCache('structure/' . ($parentId ?? 0) . '.clist');
        if (!File::putCache($articleCategoriesFile, $cacheArray)) {
            return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . Path::coreCache('structure/');
        }

        return true;
    }
}
