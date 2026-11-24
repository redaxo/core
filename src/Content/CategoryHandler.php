<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\ExtensionPoint\CategoryAdded;
use Redaxo\Core\Content\ExtensionPoint\CategoryConvertedToArticle;
use Redaxo\Core\Content\ExtensionPoint\CategoryDeleted;
use Redaxo\Core\Content\ExtensionPoint\CategoryMoved;
use Redaxo\Core\Content\ExtensionPoint\CategoryStatusChanged;
use Redaxo\Core\Content\ExtensionPoint\CategoryUpdated;
use Redaxo\Core\Core;
use Redaxo\Core\Database\DatabaseUtil;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaValues;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function array_key_exists;
use function in_array;

/**
 * Funktionensammlung für die Strukturverwaltung.
 */
final class CategoryHandler
{
    private function __construct() {}

    /**
     * Creates a new category together with its start article.
     *
     * @param array{parent_id: int|null, name: string, priority?: int, status?: int} $data Category data,
     *     `parent_id` is `null` for the root level, `name` is used for all languages and for the start article, the
     *     category is appended if `priority` is missing
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     used for all languages
     *
     * @throws UserMessageException
     */
    public static function add(array $data, array $meta = []): Category
    {
        $metaValues = new MetaValues(MetaEntity::Category, $meta);

        Type::requireKeys($data, 'parent_id', 'name');

        $categoryId = $data['parent_id'];

        if (null !== $categoryId) {
            $parent = Category::get($categoryId);
            if (!$parent) {
                throw new UserMessageException(I18n::msg('no_such_category'));
            }
            $path = '|' . implode('|', [...$parent->path, $parent->id]) . '|';
        } else {
            $path = '|';
        }

        $priority = max(1, $data['priority'] ?? self::nextPriority($categoryId));

        $startpageTemplates = [];
        if (null !== $categoryId) {
            // TemplateId vom Startartikel der jeweiligen Sprache vererben
            $sql = Sql::factory();
            $sql->setQuery('SELECT language_id, template FROM rex_article_translation WHERE article_id = ?', [$categoryId]);
            foreach ($sql as $row) {
                $template = $row->getNullableStringValue('template') ?? '';
                if ('' !== $template) {
                    $startpageTemplates[$row->getIntValue('language_id')] = $template;
                }
            }
        }

        // Alle Templates der Kategorie
        $templates = Template::getTemplatesForCategory($categoryId);

        $user = self::getUser();

        $AART = Sql::factory();
        $AART->setTable('rex_article');
        $AART->setValue('parent_id', $categoryId);
        $AART->setValue('priority', 1);
        $AART->setValue('path', $path);
        $AART->addGlobalUpdateFields($user);
        $AART->addGlobalCreateFields($user);
        $AART->insert();
        $id = $AART->getLastId();

        $shared = Sql::factory()
            ->setTable('rex_category')
            ->setValues(['id' => $id, 'priority' => $priority])
            ->addGlobalCreateFields($user)
            ->addGlobalUpdateFields($user);
        $shared->setValues($metaValues->forTable('rex_category'));
        $shared->insert();

        foreach (Language::getAllIds() as $languageId) {
            // Inherit the template from the start article of the respective language, otherwise use the default
            $templateKey = $startpageTemplates[$languageId] ?? Template::getDefaultKey();

            // Fall back to the first allowed template if the chosen one is not available in this category
            if (null === $templateKey || !isset($templates[$templateKey])) {
                $templateKey = array_key_first($templates);
            }

            Sql::factory()
                ->setTable('rex_article_translation')
                ->setValues([
                    'article_id' => $id,
                    'language_id' => $languageId,
                    'name' => $data['name'],
                    'status' => $data['status'] ?? 0,
                    'template' => $templateKey,
                ])
                ->insert();

            $translation = Sql::factory()
                ->setTable('rex_category_translation')
                ->setValues([
                    'category_id' => $id,
                    'language_id' => $languageId,
                    'name' => $data['name'],
                ]);
            $translation->setValues($metaValues->forTable('rex_category_translation'));
            $translation->insert();
        }

        // ----- PRIOR
        self::updatePriorities($categoryId, 0, $priority, $id);

        ArticleCache::delete($id);

        $category = Category::require($id);

        // ----- EXTENSION POINT
        Extension::dispatch(new CategoryAdded($category));

        return $category;
    }

    /**
     * Edits a category: the name in the given language, the priority for all languages.
     *
     * @param array{name?: string, priority?: int} $data Category data, missing keys keep their value
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     the translated ones are saved for the given language
     *
     * @throws UserMessageException
     */
    public static function edit(int $categoryId, int $languageId, array $data, array $meta = []): Category
    {
        $metaValues = new MetaValues(MetaEntity::Category, $meta);

        // --- Kategorie mit alten Daten selektieren
        $thisCat = self::select($categoryId, $languageId);

        if (1 != $thisCat->getRows()) {
            throw new UserMessageException(I18n::msg('no_such_category'));
        }

        $previous = Category::require($categoryId, $languageId);

        $translation = Sql::factory()
            ->setTable('rex_category_translation')
            ->setWhere(['category_id' => $categoryId, 'language_id' => $languageId]);
        if (array_key_exists('name', $data)) {
            $translation->setValue('name', $data['name']);
        }
        $translation->setValues($metaValues->forTable('rex_category_translation'));
        if ($translation->hasValues()) {
            $translation->update();
        }

        $oldPrio = $thisCat->getIntValue('catpriority');
        $priority = isset($data['priority']) ? max(1, $data['priority']) : $oldPrio;

        $category = Sql::factory();
        $category->setTable('rex_category');
        $category->setWhere(['id' => $categoryId]);
        $category->setValue('priority', $priority);
        $category->setValues($metaValues->forTable('rex_category'));
        $category->addGlobalUpdateFields(self::getUser());
        $category->update();

        if ($oldPrio != $priority) {
            self::updatePriorities($thisCat->getNullableIntValue('parent_id'), $priority, $oldPrio, $categoryId);
        }

        ArticleCache::delete($categoryId);

        $category = Category::require($categoryId, $languageId);

        // ----- EXTENSION POINT
        Extension::dispatch(new CategoryUpdated($category, $previous));

        return $category;
    }

    /**
     * Löscht eine Kategorie und reorganisiert die Prioritäten verbleibender Geschwister-Kategorien.
     *
     * @throws UserMessageException
     */
    public static function delete(int $categoryId): void
    {
        $category = Category::get($categoryId);
        if (!$category) {
            throw new UserMessageException(I18n::msg('category_could_not_be_deleted'));
        }

        $KAT = Sql::factory();
        $KAT->setQuery('SELECT a.id FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.parent_id = ? LIMIT 1', [$categoryId]);
        // Prüfen ob die Kategorie noch Unterkategorien besitzt
        if (0 != $KAT->getRows()) {
            throw new UserMessageException(I18n::msg('category_could_not_be_deleted') . ' ' . I18n::msg('category_still_contains_subcategories'));
        }

        $KAT->setQuery('SELECT a.id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.parent_id = ? AND c.id IS NULL LIMIT 1', [$categoryId]);
        // Prüfen ob die Kategorie noch Artikel besitzt (ausser dem Startartikel)
        if (0 != $KAT->getRows()) {
            throw new UserMessageException(I18n::msg('category_could_not_be_deleted') . ' ' . I18n::msg('category_still_contains_articles'));
        }

        ArticleHandler::deleteRecursively($categoryId);

        // ----- PRIOR
        self::updatePriorities($category->parentId, 0, 1);

        // ----- EXTENSION POINT
        Extension::dispatch(new CategoryDeleted($category));

        ComplexPermission::removeItem('structure', $categoryId);
    }

    /**
     * Ändert den Status der Kategorie.
     *
     * @param int|null $status Status auf den die Kategorie gesetzt werden soll, oder NULL wenn zum nächsten Status weitergeschaltet werden soll
     *
     * @throws UserMessageException
     */
    public static function changeStatus(int $categoryId, int $languageId, ?int $status = null): Category
    {
        $KAT = self::select($categoryId, $languageId);
        if (1 != $KAT->getRows()) {
            throw new UserMessageException(I18n::msg('no_such_category'));
        }

        $previous = Category::require($categoryId, $languageId);

        // Status wurde nicht von außen vorgegeben,
        // => zyklisch auf den nächsten Weiterschalten
        if (null === $status) {
            $newstatus = ArticleHandler::nextStatus($KAT->getIntValue('status'));
        } else {
            $newstatus = $status;
        }

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setWhere(['article_id' => $categoryId, 'language_id' => $languageId])
            ->setValue('status', $newstatus)
            ->update();

        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $categoryId])
            ->addGlobalUpdateFields(self::getUser())
            ->update();

        ArticleCache::delete($categoryId, $languageId);

        $category = Category::require($categoryId, $languageId);

        // ----- EXTENSION POINT
        Extension::dispatch(new CategoryStatusChanged($category, $previous));

        return $category;
    }

    /**
     * Recalculates the priorities of the subcategories of a category.
     *
     * @internal
     *
     * @param int|null $parentId `null` for the root level
     * @param int|null $id Id of the added or moved category, used to place it in front of or behind a category with the same priority
     */
    public static function updatePriorities(?int $parentId, int $newPrio, int $oldPrio, ?int $id = null): void
    {
        if ($newPrio != $oldPrio) {
            if ($newPrio < $oldPrio) {
                $addsql = 'desc';
            } else {
                $addsql = 'asc';
            }

            DatabaseUtil::organizePriorities(
                'rex_category',
                'priority',
                self::siblingsWhere($parentId),
                'priority, ' . (null === $id ? '' : 'id = ' . $id . ' ' . $addsql . ', ') . 'id',
            );

            ArticleCache::deleteLists($parentId);
            if (null !== $parentId) {
                ArticleCache::deleteMeta($parentId);
            }

            $ids = Sql::factory()->getArray('SELECT c.id FROM rex_category c JOIN rex_article a ON a.id = c.id WHERE a.parent_id <=> ?', [$parentId]);
            foreach ($ids as $id) {
                ArticleCache::deleteMeta((int) $id['id']);
            }
        }
    }

    /**
     * Moves a category into another category.
     *
     * @param int|null $toCat `null` for the root level
     *
     * @throws UserMessageException
     */
    public static function move(int $fromCat, ?int $toCat): void
    {
        if ($fromCat === $toCat) {
            // kann nicht in gleiche kategroie kopiert werden
            throw new UserMessageException(I18n::msg('content_error_movecategory'));
        }

        // kategorien vorhanden ?
        // ist die zielkategorie im pfad der quellkategeorie ?
        $fcat = Sql::factory();
        $fcat->setQuery('SELECT a.* FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$fromCat]);

        $tcat = Sql::factory();
        $tcat->setQuery('SELECT a.* FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$toCat]);

        if (1 != $fcat->getRows() || (1 != $tcat->getRows() && null !== $toCat)) {
            throw new UserMessageException(I18n::msg('no_such_category'));
        }
        if (null !== $toCat) {
            $tcats = explode('|', $tcat->getStringValue('path'));
            if (in_array($fromCat, $tcats)) {
                // zielkategorie ist in quellkategorie -> nicht verschiebbar
                throw new UserMessageException(I18n::msg('content_error_movecategory'));
            }
        }

        $fromParentId = $fcat->getNullableIntValue('parent_id');
        $previous = Category::require($fromCat);

        // ----- folgende cats regenerate
        $RC = [];
        foreach ([$fromParentId, $fromCat, $toCat] as $regenerateId) {
            if (null !== $regenerateId) {
                $RC[$regenerateId] = 1;
            }
        }

        if (null !== $toCat) {
            $toPath = $tcat->getStringValue('path') . $toCat . '|';
        } else {
            $toPath = '|';
        }

        $fromPath = $fcat->getStringValue('path') . $fromCat . '|';

        $gcats = Sql::factory();
        $gcats->setQuery('SELECT id, path FROM rex_article WHERE path LIKE ?', [$fromPath . '%']);

        $up = Sql::factory();
        foreach ($gcats as $gcat) {
            // make update
            $newPath = $toPath . $fromCat . '|' . str_replace($fromPath, '', $gcat->getStringValue('path'));
            $icid = $gcat->getIntValue('id');

            // path aendern und speichern
            $up->setTable('rex_article');
            $up->setWhere(['id' => $icid]);
            $up->setValue('path', $newPath);
            $up->update();

            // cat in gen eintragen
            $RC[$icid] = 1;
        }

        $gmax = Sql::factory();
        $gmax->setQuery('SELECT MAX(c.priority) AS priority FROM rex_category c JOIN rex_article a ON a.id = c.id WHERE a.parent_id <=> ?', [$toCat]);
        $priority = $gmax->getNullableIntValue('priority') ?? 0;

        $user = self::getUser();

        $up = Sql::factory();
        $up->setTable('rex_article');
        $up->setWhere(['id' => $fromCat]);
        $up->setValue('path', $toPath);
        $up->setValue('parent_id', $toCat);
        $up->addGlobalUpdateFields($user);
        $up->update();

        Sql::factory()
            ->setTable('rex_category')
            ->setWhere(['id' => $fromCat])
            ->setValue('priority', $priority + 1)
            ->addGlobalUpdateFields($user)
            ->update();

        // ----- generiere artikel neu - ohne neue inhaltsgenerierung
        foreach ($RC as $id => $key) {
            ArticleCache::delete($id);
        }

        self::updatePriorities($fromParentId, 0, 1);

        Extension::dispatch(new CategoryMoved(Category::require($fromCat), $previous));
    }

    /**
     * Konvertiert eine Kategorie in einen Artikel.
     *
     * @throws UserMessageException
     */
    public static function convertToArticle(int $categoryId): void
    {
        $sql = Sql::factory();

        // Kategorie muss leer sein
        $sql->setQuery('SELECT id FROM rex_article WHERE parent_id = ? LIMIT 1', [$categoryId]);
        if (0 != $sql->getRows()) {
            throw new UserMessageException(I18n::msg('content_toarticle_failed'));
        }

        $sql->setQuery('SELECT a.parent_id FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$categoryId]);
        if (1 != $sql->getRows()) {
            throw new UserMessageException(I18n::msg('content_toarticle_failed'));
        }
        $parentId = $sql->getNullableIntValue('parent_id');
        $previous = Category::require($categoryId);

        // the translations of the category are removed by the foreign key
        $sql->setQuery('DELETE FROM rex_category WHERE id = ?', [$categoryId]);

        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $categoryId])
            ->setValue('priority', 99999)
            ->addGlobalUpdateFields(self::getUser())
            ->update();

        ArticleHandler::updatePriorities($parentId, 1, 0);

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($categoryId);

        Extension::dispatch(new CategoryConvertedToArticle(Article::require($categoryId), $previous));
    }

    /** Selects the category with its start article and the translations in the given language. */
    private static function select(int $id, int $languageId): Sql
    {
        $sql = Sql::factory();
        $sql->setQuery('
            SELECT a.*, t.*, c.priority AS catpriority, ct.name AS catname
            FROM rex_article a
            JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = ?
            JOIN rex_category c ON c.id = a.id
            JOIN rex_category_translation ct ON ct.category_id = c.id AND ct.language_id = t.language_id
            WHERE a.id = ?
        ', [$languageId, $id]);

        return $sql;
    }

    private static function siblingsWhere(?int $parentId): string
    {
        return 'id IN (SELECT id FROM rex_article WHERE parent_id ' . (null === $parentId ? 'IS NULL' : '= ' . $parentId) . ')';
    }

    private static function nextPriority(?int $parentId): int
    {
        $sql = Sql::factory()->setQuery('SELECT MAX(priority) AS priority FROM rex_category WHERE ' . self::siblingsWhere($parentId));

        return ($sql->getNullableIntValue('priority') ?? 0) + 1;
    }

    private static function getUser(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
