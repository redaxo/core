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
            File::delete($cachePath . $id . '.' . $otherClangId . '.category');
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

        $cachePath = Path::coreCache('structure/');

        $categories = [];
        foreach (self::selectRows('rex_category', 'rex_category_translation', 'category_id', $articleId, $languageId) as $row) {
            $categories[(int) $row['language_id']] = $row;
        }

        foreach (self::selectRows('rex_article', 'rex_article_translation', 'article_id', $articleId, $languageId) as $article) {
            $rowLanguageId = (int) $article['language_id'];
            $category = $categories[$rowLanguageId] ?? null;
            $article['startarticle'] = (int) (null !== $category);

            if (!File::putCache($cachePath . $articleId . '.' . $rowLanguageId . '.article', $article)) {
                return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . $cachePath;
            }

            if (null === $category) {
                continue;
            }

            // parent, path and status belong to the start article; they are copied over so that a category can be
            // built from its own cache file alone
            $category['parent_id'] = $article['parent_id'];
            $category['path'] = $article['path'];
            $category['status'] = $article['status'];

            if (!File::putCache($cachePath . $articleId . '.' . $rowLanguageId . '.category', $category)) {
                return I18n::msg('article_could_not_be_generated') . ' ' . I18n::msg('check_rights_in_directory') . $cachePath;
            }
        }

        return true;
    }

    /**
     * Reads the rows of a structure table joined with its translation table, one flat row per language.
     *
     * @param non-empty-string $table
     * @param non-empty-string $translationTable
     * @param non-empty-string $foreignKey name of the column referencing $table in $translationTable
     *
     * @return list<array<string, scalar|null>>
     */
    private static function selectRows(string $table, string $translationTable, string $foreignKey, int $id, ?int $languageId): array
    {
        $query = '
            SELECT e.*, t.*
            FROM ' . $table . ' e
            JOIN ' . $translationTable . ' t ON t.' . $foreignKey . ' = e.id
            WHERE e.id = ?
        ';
        $params = [$id];
        if (null !== $languageId) {
            $query .= ' AND t.language_id = ?';
            $params[] = $languageId;
        }

        $sql = Sql::factory();
        $sql->setQuery($query, $params);
        $fieldnames = $sql->getFieldnames();

        $rows = [];
        foreach ($sql as $row) {
            $values = [];
            foreach ($fieldnames as $field) {
                if ($foreignKey === $field) {
                    continue;
                }
                $values[$field] = match ($field) {
                    'createdate', 'updatedate' => $row->getDateTimeValue($field),
                    default => $row->getValue($field),
                };
            }
            $rows[] = $values;
        }

        return $rows;
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
        // the lists are shared by all languages, the names of the start language break priority ties;
        // the start article is listed among the articles of its own category
        $GC->setQuery('
            SELECT a.id
            FROM rex_article a
            JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = :language
            LEFT JOIN rex_category c ON c.id = a.id
            WHERE (a.parent_id <=> :id AND c.id IS NULL) OR (a.id = :id AND c.id IS NOT NULL)
            ORDER BY a.priority, t.name
        ', ['id' => $parentId, 'language' => Language::getStartId()]);

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
        $GC->setQuery('
            SELECT c.id
            FROM rex_category c
            JOIN rex_article a ON a.id = c.id
            JOIN rex_category_translation ct ON ct.category_id = c.id AND ct.language_id = :language
            WHERE a.parent_id <=> :id
            ORDER BY c.priority, ct.name
        ', ['id' => $parentId, 'language' => Language::getStartId()]);

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
