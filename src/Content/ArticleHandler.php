<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\ExtensionPoint\ArticleAdded;
use Redaxo\Core\Content\ExtensionPoint\ArticleConvertedToCategory;
use Redaxo\Core\Content\ExtensionPoint\ArticleConvertedToStartArticle;
use Redaxo\Core\Content\ExtensionPoint\ArticleCopied;
use Redaxo\Core\Content\ExtensionPoint\ArticleDeleted;
use Redaxo\Core\Content\ExtensionPoint\ArticleDeleting;
use Redaxo\Core\Content\ExtensionPoint\ArticleMoved;
use Redaxo\Core\Content\ExtensionPoint\ArticleStatusChanged;
use Redaxo\Core\Content\ExtensionPoint\ArticleStatusTypes;
use Redaxo\Core\Content\ExtensionPoint\ArticleUpdated;
use Redaxo\Core\Content\ExtensionPoint\ArticleUsageCheck;
use Redaxo\Core\Core;
use Redaxo\Core\Database\DatabaseUtil;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Reference;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaUsage;
use Redaxo\Core\MetaInfo\MetaValues;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use Redaxo\Core\Util\UsageList;

use function array_key_exists;
use function count;
use function implode;
use function in_array;

final class ArticleHandler
{
    private function __construct() {}

    /**
     * Creates a new article.
     *
     * @param array{category_id: int|null, name: string, priority?: int, template?: string|null} $data Article data,
     *     `category_id` is `null` for the root level, `name` is used for all languages, the article is appended if
     *     `priority` is missing, and `template` falls back to the first one allowed in the category
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     used for all languages
     *
     * @throws UserMessageException
     */
    public static function add(array $data, array $meta = []): Article
    {
        $metaValues = new MetaValues(MetaEntity::Article, $meta);

        Type::requireKeys($data, 'category_id', 'name');

        $categoryId = $data['category_id'];

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
        $template = self::allowedTemplate($categoryId, $data['template'] ?? null);

        $user = self::getUser();

        $AART = Sql::factory();
        $AART->setTable('rex_article');
        $AART->setValue('parent_id', $categoryId);
        $AART->setValue('priority', $priority);
        $AART->setValue('path', $path);
        $AART->setValues($metaValues->forTable('rex_article'));
        $AART->addGlobalCreateFields($user);
        $AART->addGlobalUpdateFields($user);
        $AART->insert();
        $id = $AART->getLastId();

        foreach (Language::getAllIds() as $languageId) {
            Sql::factory()
                ->setTable('rex_article_translation')
                ->setValues([
                    'article_id' => $id,
                    'language_id' => $languageId,
                    'name' => $data['name'],
                    'status' => 0,
                    'template' => $template,
                ])
                ->setValues($metaValues->forTable('rex_article_translation'))
                ->insert();
        }

        self::updatePriorities($categoryId, 0, $priority);

        ArticleCache::delete($id);

        $article = Article::require($id);

        // ----- EXTENSION POINT
        Extension::dispatch(new ArticleAdded($article));

        return $article;
    }

    /**
     * Edits an article: name and template in the given language, the priority for all languages.
     *
     * @param array{name?: string, priority?: int, template?: string|null} $data Article data, missing keys keep their
     *     value, `template` falls back to the first one allowed in the category
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     the translated ones are saved for the given language
     *
     * @throws UserMessageException
     */
    public static function edit(int $articleId, int $languageId, array $data, array $meta = []): Article
    {
        $metaValues = new MetaValues(MetaEntity::Article, $meta);

        $thisArt = self::select($articleId, $languageId);

        if (1 != $thisArt->getRows()) {
            throw new UserMessageException(I18n::msg('article_doesnt_exist'));
        }

        $previous = Article::require($articleId, $languageId);

        $translation = Sql::factory()
            ->setTable('rex_article_translation')
            ->setWhere(['article_id' => $articleId, 'language_id' => $languageId]);
        if (array_key_exists('name', $data)) {
            $translation->setValue('name', $data['name']);
        }
        if (array_key_exists('template', $data)) {
            $translation->setValue('template', self::allowedTemplate($previous->categoryId, $data['template']));
        }
        $translation->setValues($metaValues->forTable('rex_article_translation'));
        if ($translation->hasValues()) {
            $translation->update();
        }

        $oldPrio = $thisArt->getIntValue('priority');
        $priority = isset($data['priority']) ? max(1, $data['priority']) : $oldPrio;

        $EA = Sql::factory();
        $EA->setTable('rex_article');
        $EA->setWhere(['id' => $articleId]);
        $EA->setValue('priority', $priority);
        $EA->setValues($metaValues->forTable('rex_article'));
        $EA->addGlobalUpdateFields(self::getUser());
        $EA->update();

        if ($oldPrio != $priority) {
            self::updatePriorities($previous->categoryId, $priority, $oldPrio);
        }

        ArticleCache::delete($articleId);

        $article = Article::require($articleId, $languageId);

        // ----- EXTENSION POINT
        Extension::dispatch(new ArticleUpdated($article, $previous));

        return $article;
    }

    /**
     * Löscht einen Artikel und reorganisiert die Prioritäten verbleibender Geschwister-Artikel.
     *
     * @throws UserMessageException
     */
    public static function delete(int $articleId): void
    {
        $Art = Sql::factory();
        $Art->setQuery('SELECT a.* FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$articleId]);

        if (1 != $Art->getRows()) {
            throw new UserMessageException(I18n::msg('article_doesnt_exist'));
        }

        $article = Article::require($articleId);

        self::deleteRecursively($articleId);

        // ----- PRIOR
        self::updatePriorities($article->categoryId, 0, 1);

        // ----- EXTENSION POINT
        Extension::dispatch(new ArticleDeleted($article));
    }

    /** Returns the places using the article, except the article itself and the category it is the start article of. */
    public static function findUsages(Article $article): UsageList
    {
        $usages = new UsageList();
        foreach (ArticleSliceReference::findArticles(Reference::article($article->id)) as ['articleId' => $articleId, 'languageId' => $languageId]) {
            if ($articleId !== $article->id) {
                $usages->add(I18n::rawMsg('usage_articles_content'), Article::get($articleId, $languageId)->name ?? (string) $articleId, Url::backendPage('content', ['article_id' => $articleId, 'mode' => 'edit', 'language' => $languageId]));
            }
        }

        MetaUsage::addArticleUsages($usages, $article->id);

        Extension::dispatch(new ArticleUsageCheck($article, $usages));

        return $usages;
    }

    /**
     * Deletes an article, a start article together with all articles of its category, without checking permissions.
     *
     * @internal
     *
     * @throws UserMessageException
     */
    public static function deleteRecursively(int $id): void
    {
        if ($id == Article::getSiteStartArticleId()) {
            throw new UserMessageException(I18n::msg('cant_delete_sitestartarticle'));
        }
        if ($id == Article::getNotfoundArticleId()) {
            throw new UserMessageException(I18n::msg('cant_delete_notfoundarticle'));
        }

        $ART = Sql::factory();
        $ART->setQuery('SELECT a.*, c.id IS NOT NULL AS startarticle FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$id]);

        if (0 === $ART->getRows()) {
            throw new UserMessageException(I18n::msg('category_doesnt_exist'));
        }

        $article = Article::require($id);
        $usages = self::findUsages($article);
        if (!$usages->isEmpty()) {
            throw new UserMessageException(I18n::msg('article_still_in_use', $article->name) . $usages->render());
        }

        $parentId = $ART->getNullableIntValue('parent_id');
        Extension::dispatch(new ArticleDeleting($article));

        if ($ART->getBoolValue('startarticle')) {
            $SART = Sql::factory();
            $SART->setQuery('SELECT id FROM rex_article WHERE parent_id = ?', [$id]);
            foreach ($SART as $child) {
                self::deleteRecursively($child->getIntValue('id'));
            }
        }

        ArticleCache::delete($id);
        // the translations, the category row, the slices and their history are removed by the foreign keys
        $ART->setQuery('DELETE FROM rex_article WHERE id = ?', [$id]);

        // --------------------------------------------------- Listen generieren
        ArticleCache::deleteLists($parentId);
    }

    /**
     * Ändert den Status des Artikels.
     *
     * @param int|null $status Status auf den der Artikel gesetzt werden soll, oder NULL wenn zum nächsten Status weitergeschaltet werden soll
     *
     * @throws UserMessageException
     */
    public static function changeStatus(int $articleId, int $languageId, ?int $status = null): Article
    {
        $GA = self::select($articleId, $languageId);
        if (1 != $GA->getRows()) {
            throw new UserMessageException(I18n::msg('article_doesnt_exist'));
        }

        $previous = Article::require($articleId, $languageId);

        // Status wurde nicht von außen vorgegeben,
        // => zyklisch auf den nächsten Weiterschalten
        if (null === $status) {
            $newstatus = self::nextStatus($GA->getIntValue('status'));
        } else {
            $newstatus = $status;
        }

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setWhere(['article_id' => $articleId, 'language_id' => $languageId])
            ->setValue('status', $newstatus)
            ->update();

        self::touch($articleId);

        ArticleCache::delete($articleId, $languageId);

        $article = Article::require($articleId, $languageId);

        // ----- EXTENSION POINT
        Extension::dispatch(new ArticleStatusChanged($article, $previous));

        return $article;
    }

    /**
     * Returns the status types of articles, which apply to categories as well.
     *
     * @return list<array{string, string, string}> Label, CSS class and icon, indexed by the status value
     */
    public static function statusTypes(): array
    {
        /** @var list<array{string, string, string}> $artStatusTypes */
        static $artStatusTypes;

        if (!$artStatusTypes) {
            $artStatusTypes = Extension::dispatch(new ArticleStatusTypes([
                [I18n::msg('status_offline'), 'rex-u-offline', 'rex-icon-offline'],
                [I18n::msg('status_online'), 'rex-u-online', 'rex-icon-online'],
            ]));
        }

        return $artStatusTypes;
    }

    public static function nextStatus(int $currentStatus): int
    {
        $artStatusTypes = self::statusTypes();
        return ($currentStatus + 1) % count($artStatusTypes);
    }

    public static function prevStatus(int $currentStatus): int
    {
        $artStatusTypes = self::statusTypes();
        if (($currentStatus - 1) < 0) {
            return count($artStatusTypes) - 1;
        }

        return ($currentStatus - 1) % count($artStatusTypes);
    }

    /**
     * Recalculates the priorities of the articles in a category.
     *
     * @internal
     *
     * @param int|null $parentId `null` for the root level
     */
    public static function updatePriorities(?int $parentId, int $newPrio, int $oldPrio): void
    {
        if ($newPrio != $oldPrio) {
            if ($newPrio < $oldPrio) {
                $addsql = 'desc';
            } else {
                $addsql = 'asc';
            }

            DatabaseUtil::organizePriorities(
                'rex_article',
                'priority',
                self::siblingsWhere($parentId),
                'priority,updatedate ' . $addsql,
            );

            ArticleCache::deleteLists($parentId);
            if (null !== $parentId) {
                ArticleCache::deleteMeta($parentId);
            }

            $ids = Sql::factory()->getArray('SELECT a.id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE c.id IS NULL AND a.parent_id <=> ?', [$parentId]);
            foreach ($ids as $id) {
                ArticleCache::deleteMeta((int) $id['id']);
            }
        }
    }

    /**
     * Konvertiert einen Artikel in eine Kategorie.
     *
     * @throws UserMessageException
     */
    public static function convertToCategory(int $artId): void
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT a.parent_id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$artId]);
        if (1 != $sql->getRows()) {
            throw new UserMessageException(I18n::msg('content_tocategory_failed'));
        }
        $parentId = $sql->getNullableIntValue('parent_id');
        $previous = Article::require($artId);

        $user = self::getUser();

        Sql::factory()
            ->setTable('rex_category')
            ->setValues(['id' => $artId, 'priority' => 99999])
            ->addGlobalCreateFields($user)
            ->addGlobalUpdateFields($user)
            ->insert();

        // the category starts out with the article name in every language
        $sql->setQuery('INSERT INTO rex_category_translation (category_id, language_id, name) SELECT article_id, language_id, name FROM rex_article_translation WHERE article_id = ?', [$artId]);

        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $artId])
            ->setValue('priority', 1)
            ->addGlobalUpdateFields($user)
            ->update();

        CategoryHandler::updatePriorities($parentId, 1, 0);

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($artId);

        Extension::dispatch(new ArticleConvertedToCategory(Category::require($artId), $previous));
    }

    /**
     * Makes an article the start article of its category.
     *
     * The category keeps its position, names and meta values, but continues under the id of the new start article,
     * while the previous start article becomes a plain article inside the category.
     *
     * @throws UserMessageException
     */
    public static function convertToStartArticle(int $neuId): void
    {
        $GAID = [];

        // neuen startartikel holen und schauen ob da
        $neu = Sql::factory();
        $neu->setQuery('SELECT a.* FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$neuId]);
        if (1 != $neu->getRows()) {
            throw new UserMessageException(I18n::msg('content_tostartarticle_failed'));
        }
        $altId = $neu->getNullableIntValue('parent_id');

        // in oberster kategorie dann return
        if (null === $altId) {
            throw new UserMessageException(I18n::msg('content_tostartarticle_failed'));
        }

        // alten startartikel
        $alt = Sql::factory();
        $alt->setQuery('SELECT a.* FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$altId]);
        if (1 != $alt->getRows()) {
            throw new UserMessageException(I18n::msg('content_tostartarticle_failed'));
        }
        $parentId = $alt->getNullableIntValue('parent_id');

        $user = self::getUser();

        // the category continues under the id of the new start article; the foreign keys carry its translations and
        // the children (the new start article among them) along
        Sql::factory()
            ->setTable('rex_category')
            ->setWhere(['id' => $altId])
            ->setValue('id', $neuId)
            ->addGlobalUpdateFields($user)
            ->update();
        $sql = Sql::factory();

        // the articles swap their position: the new start article takes the place of the category
        $alt2 = Sql::factory();
        $alt2->setTable('rex_article');
        $alt2->setWhere(['id' => $altId]);
        $alt2->setValue('parent_id', $neuId);
        $alt2->setValue('path', $neu->getValue('path'));
        $alt2->setValue('priority', $neu->getValue('priority'));
        $alt2->addGlobalUpdateFields($user);
        $alt2->update();

        $neu2 = Sql::factory();
        $neu2->setTable('rex_article');
        $neu2->setWhere(['id' => $neuId]);
        $neu2->setValue('parent_id', $parentId);
        $neu2->setValue('path', $alt->getValue('path'));
        $neu2->setValue('priority', $alt->getValue('priority'));
        $neu2->addGlobalUpdateFields($user);
        $neu2->update();

        // the status belongs to the category, so it moves with it in every language
        foreach (Language::getAllIds() as $languageId) {
            $altTranslation = Sql::factory();
            $altTranslation->setQuery('SELECT status FROM rex_article_translation WHERE article_id = ? AND language_id = ?', [$altId, $languageId]);
            $neuTranslation = Sql::factory();
            $neuTranslation->setQuery('SELECT status FROM rex_article_translation WHERE article_id = ? AND language_id = ?', [$neuId, $languageId]);
            if (1 != $altTranslation->getRows() || 1 != $neuTranslation->getRows()) {
                continue;
            }

            $sql->setQuery('UPDATE rex_article_translation SET status = ? WHERE article_id = ? AND language_id = ?', [$neuTranslation->getValue('status'), $altId, $languageId]);
            $sql->setQuery('UPDATE rex_article_translation SET status = ? WHERE article_id = ? AND language_id = ?', [$altTranslation->getValue('status'), $neuId, $languageId]);
        }

        // alle artikel suchen nach |art_id| und pfade ersetzen
        $articles = Sql::factory();
        $ia = Sql::factory();
        $articles->setQuery('SELECT id, path FROM rex_article WHERE path LIKE ?', ['%|' . $altId . '|%']);
        foreach ($articles as $article) {
            $iid = $article->getIntValue('id');

            $ia->setTable('rex_article');
            $ia->setWhere(['id' => $iid]);
            $ia->setValue('path', str_replace("|$altId|", "|$neuId|", $article->getStringValue('path')));
            $ia->update();
            $GAID[$iid] = $iid;
        }

        $GAID[$neuId] = $neuId;
        $GAID[$altId] = $altId;
        if (null !== $parentId) {
            $GAID[$parentId] = $parentId;
        }

        foreach ($GAID as $gid) {
            ArticleCache::delete($gid);
        }

        ComplexPermission::replaceItem('structure', $altId, $neuId);

        Extension::dispatch(new ArticleConvertedToStartArticle(Article::require($neuId), Article::require($altId)));
    }

    /**
     * Copies meta values from one article to another.
     *
     * Shared columns are copied once for all languages, translatable ones from `$fromLanguageId` to `$toLanguageId`.
     * Category columns are only copied if both articles are categories.
     *
     * @param list<string> $params Names of the columns to copy
     */
    public static function copyMeta(int $fromId, int $toId, int $fromLanguageId = 1, int $toLanguageId = 1, array $params = []): void
    {
        if ($fromId === $toId && $fromLanguageId === $toLanguageId) {
            throw new InvalidArgumentException('Source and target of the meta copy must differ.');
        }

        $tables = [
            'rex_article' => [['id' => $fromId], ['id' => $toId]],
            'rex_article_translation' => [['article_id' => $fromId, 'language_id' => $fromLanguageId], ['article_id' => $toId, 'language_id' => $toLanguageId]],
            'rex_category' => [['id' => $fromId], ['id' => $toId]],
            'rex_category_translation' => [['category_id' => $fromId, 'language_id' => $fromLanguageId], ['category_id' => $toId, 'language_id' => $toLanguageId]],
        ];

        $user = self::getUser();
        $copied = [];
        $sharedCopied = false;
        foreach ($tables as $table => [$from, $to]) {
            $columns = array_intersect($params, array_keys(Table::get($table)->getColumns()));
            if ([] === $columns) {
                continue;
            }

            $source = Sql::factory();
            $source->setTable($table);
            $source->setWhere($from);
            $source->select();
            if (1 != $source->getRows()) {
                continue;
            }

            $target = Sql::factory();
            $target->setTable($table);
            $target->setWhere($to);
            foreach ($columns as $column) {
                $target->setValue($column, $source->getValue($column));
            }
            if (in_array($table, ['rex_article', 'rex_category'], true)) {
                $target->addGlobalUpdateFields($user);
            }
            $target->update();

            $copied[] = $table;
            $sharedCopied = $sharedCopied || !str_ends_with($table, '_translation');
        }

        if ([] === $copied) {
            return;
        }

        // the owner of a changed translation counts as updated as well
        if (in_array('rex_article_translation', $copied, true) && !in_array('rex_article', $copied, true)) {
            self::touch($toId);
        }
        if (in_array('rex_category_translation', $copied, true) && !in_array('rex_category', $copied, true)) {
            Sql::factory()
                ->setTable('rex_category')
                ->setWhere(['id' => $toId])
                ->addGlobalUpdateFields($user)
                ->update();
        }

        if ($sharedCopied) {
            ArticleCache::deleteMeta($toId);
        } else {
            ArticleCache::deleteMeta($toId, $toLanguageId);
        }
    }

    /**
     * Copies an article (or the start article of a category) as a plain article into another category.
     *
     * @param int|null $toCatId `null` for the root level
     *
     * @throws UserMessageException
     */
    public static function copy(int $id, ?int $toCatId): Article
    {
        $user = self::getUser();

        $fromSql = Sql::factory();
        $fromSql->setQuery('SELECT * FROM rex_article WHERE id = ?', [$id]);
        if (1 != $fromSql->getRows()) {
            throw new UserMessageException(I18n::msg('article_doesnt_exist'));
        }

        $path = '|';
        if (null !== $toCatId) {
            $toSql = Sql::factory();
            $toSql->setQuery('SELECT a.path FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$toCatId]);
            if (1 != $toSql->getRows()) {
                throw new UserMessageException(I18n::msg('no_such_category'));
            }
            $path = $toSql->getStringValue('path') . $toCatId . '|';
        }

        $artSql = Sql::factory();
        $artSql->setTable('rex_article');
        $artSql->setValue('parent_id', $toCatId);
        $artSql->setValue('path', $path);
        $artSql->setValue('priority', 99_999); // Artikel als letzten Artikel in die neue Kat einfügen
        $artSql->addGlobalUpdateFields($user);
        $artSql->addGlobalCreateFields($user);

        // schon gesetzte Felder nicht wieder überschreiben
        $dontCopy = ['id', 'parent_id', 'path', 'priority', 'updatedate', 'updateuser', 'createdate', 'createuser'];
        foreach (array_diff($fromSql->getFieldnames(), $dontCopy) as $fldName) {
            $artSql->setValue($fldName, $fromSql->getValue($fldName));
        }

        $artSql->insert();
        $newId = $artSql->getLastId();

        foreach (Language::getAllIds() as $languageId) {
            $fromTranslation = Sql::factory();
            $fromTranslation->setQuery('SELECT * FROM rex_article_translation WHERE article_id = ? AND language_id = ?', [$id, $languageId]);
            if (1 != $fromTranslation->getRows()) {
                continue;
            }

            $translation = Sql::factory();
            $translation->setTable('rex_article_translation');
            $translation->setValue('article_id', $newId);
            $translation->setValue('language_id', $languageId);
            $translation->setValue('name', $fromTranslation->getStringValue('name') . ' ' . I18n::msg('structure_copy'));
            $translation->setValue('status', 0); // Kopierter Artikel offline setzen

            $dontCopy = ['article_id', 'language_id', 'name', 'status'];
            foreach (array_diff($fromTranslation->getFieldnames(), $dontCopy) as $fldName) {
                $translation->setValue($fldName, $fromTranslation->getValue($fldName));
            }

            $translation->insert();

            $revisions = Sql::factory();
            $revisions->setQuery('select revision from rex_article_slice where priority=1 AND article_id=? AND language_id=? GROUP BY revision', [$id, $languageId]);
            foreach ($revisions as $rev) {
                // FIXME this dependency is very ugly!
                // ArticleSlices kopieren
                ContentHandler::copyContent($id, $newId, $languageId, $languageId, $rev->getIntValue('revision'));
            }
        }

        // Prios neu berechnen
        self::updatePriorities($toCatId, 1, 0);

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        if (null !== $toCatId) {
            ArticleCache::delete($toCatId);
        }

        $article = Article::require($newId);

        Extension::dispatch(new ArticleCopied($article, Article::require($id)));

        return $article;
    }

    /**
     * Moves an article into another category.
     *
     * @param int|null $fromCatId `null` for the root level
     * @param int|null $toCatId `null` for the root level
     *
     * @throws UserMessageException
     */
    public static function move(int $id, ?int $fromCatId, ?int $toCatId): void
    {
        if ($fromCatId === $toCatId) {
            throw new UserMessageException(I18n::msg('content_errormovearticle'));
        }

        // validierung der id & from_cat_id
        $fromSql = Sql::factory();
        $fromSql->setQuery('SELECT a.id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL AND a.parent_id <=> ?', [$id, $fromCatId]);
        if (1 != $fromSql->getRows()) {
            throw new UserMessageException(I18n::msg('article_doesnt_exist'));
        }

        // validierung der to_cat_id
        $path = '|';
        if (null !== $toCatId) {
            $toSql = Sql::factory();
            $toSql->setQuery('SELECT a.path FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$toCatId]);
            if (1 != $toSql->getRows()) {
                throw new UserMessageException(I18n::msg('no_such_category'));
            }
            $path = $toSql->getStringValue('path') . $toCatId . '|';
        }

        $previous = Article::require($id);

        $artSql = Sql::factory();
        $artSql->setTable('rex_article');
        $artSql->setWhere(['id' => $id]);
        $artSql->setValue('parent_id', $toCatId);
        $artSql->setValue('path', $path);
        // Artikel als letzten Artikel in die neue Kat einfügen
        $artSql->setValue('priority', 99999);
        $artSql->addGlobalUpdateFields(self::getUser());
        $artSql->update();

        // Prios neu berechnen
        self::updatePriorities($toCatId, 1, 0);
        self::updatePriorities($fromCatId, 1, 0);

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        foreach ([$fromCatId, $toCatId] as $categoryId) {
            if (null !== $categoryId) {
                ArticleCache::delete($categoryId);
            }
        }

        Extension::dispatch(new ArticleMoved(Article::require($id), $previous));
    }

    /** Selects the article together with its translation in the given language. */
    /** The start article is listed among the articles of its own category. */
    private static function siblingsWhere(?int $parentId): string
    {
        return null === $parentId
            ? 'parent_id IS NULL AND id NOT IN (SELECT id FROM rex_category)'
            : '(parent_id = ' . $parentId . ' AND id NOT IN (SELECT id FROM rex_category)) OR id = ' . $parentId;
    }

    private static function nextPriority(?int $parentId): int
    {
        $sql = Sql::factory()->setQuery('SELECT MAX(priority) AS priority FROM rex_article WHERE ' . self::siblingsWhere($parentId));

        return ($sql->getNullableIntValue('priority') ?? 0) + 1;
    }

    /** Falls back to the first template allowed in the category if the given one is not. */
    private static function allowedTemplate(?int $categoryId, ?string $template): ?string
    {
        $templates = Template::getTemplatesForCategory($categoryId);

        if (null === $template || !isset($templates[$template])) {
            return array_key_first($templates);
        }

        return $template;
    }

    private static function select(int $id, int $languageId): Sql
    {
        $sql = Sql::factory();
        $sql->setQuery('
            SELECT a.*, t.*, c.id IS NOT NULL AS startarticle
            FROM rex_article a
            JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = ?
            LEFT JOIN rex_category c ON c.id = a.id
            WHERE a.id = ?
        ', [$languageId, $id]);

        return $sql;
    }

    /** Marks the article as updated. */
    private static function touch(int $id): void
    {
        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $id])
            ->addGlobalUpdateFields(self::getUser())
            ->update();
    }

    private static function getUser(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
