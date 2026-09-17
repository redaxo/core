<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Database\Util;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function array_key_exists;
use function count;
use function in_array;

final class ArticleHandler
{
    private function __construct() {}

    /**
     * Creates a new article.
     *
     * @param array{category_id: int|null, priority: int, name: string, template?: string} $data Article data,
     *     `category_id` is `null` for the root level, `name` is used for all languages
     *
     * @throws ApiFunctionException
     *
     * @return string A status message
     */
    public static function addArticle(array $data): string
    {
        self::reqKey($data, 'category_id');
        self::reqKey($data, 'priority');
        self::reqKey($data, 'name');

        if ($data['priority'] <= 0) {
            $data['priority'] = 1;
        }

        $categoryId = $data['category_id'];

        if (null !== $categoryId) {
            $parent = Category::get($categoryId);
            if (!$parent) {
                throw new ApiFunctionException('Target category with ID "' . $categoryId . '" does not exist.');
            }
            $path = '|' . implode('|', [...$parent->path, $parent->id]) . '|';
        } else {
            $path = '|';
        }

        $templates = Template::getTemplatesForCategory($categoryId);
        $data['template'] = isset($data['template']) ? Type::string($data['template']) : null;

        // Wenn Template nicht vorhanden, dann entweder erlaubtes nehmen
        // oder leer setzen.
        if (null === $data['template'] || !isset($templates[$data['template']])) {
            $data['template'] = array_key_first($templates);
        }

        $user = self::getUser();

        $AART = Sql::factory();
        $AART->setTable('rex_article');
        $AART->setValue('parent_id', $categoryId);
        $AART->setValue('priority', $data['priority']);
        $AART->setValue('path', $path);
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
                    'template' => $data['template'],
                ])
                ->insert();
        }

        self::newArtPrio($categoryId, 0, $data['priority']);

        ArticleCache::delete($id);

        // ----- EXTENSION POINT
        $message = Extension::dispatch(new ExtensionPoint('ART_ADDED', I18n::msg('article_added'), [
            'id' => $id,
            'status' => 0,
            'name' => $data['name'],
            'parent_id' => $categoryId,
            'priority' => $data['priority'],
            'path' => $path,
            'template_key' => $data['template'],
            'data' => $data,
        ]));

        return $message;
    }

    /**
     * Edits an article: name and template in the given language, the priority for all languages.
     *
     * @param array{name: string, priority?: int, template?: string|null} $data Article data
     *
     * @throws ApiFunctionException
     *
     * @return string A status message
     */
    public static function editArticle(int $articleId, int $languageId, array $data): string
    {
        self::reqKey($data, 'name');

        $thisArt = self::select($articleId, $languageId);

        if (1 != $thisArt->getRows()) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and language "' . $languageId . '"!');
        }

        $ooArt = Article::require($articleId, $languageId);
        $data['category_id'] = $ooArt->categoryId;

        $templates = Template::getTemplatesForCategory($data['category_id']);
        $data['template'] = isset($data['template']) ? Type::string($data['template']) : null;

        // Wenn Template nicht vorhanden, dann entweder erlaubtes nehmen
        // oder leer setzen.
        if (null === $data['template'] || !isset($templates[$data['template']])) {
            $data['template'] = array_key_first($templates);
        }

        if (isset($data['priority'])) {
            if ($data['priority'] <= 0) {
                $data['priority'] = 1;
            }
        }

        // complete remaining optional aprams
        $data['path'] = $thisArt->getValue('path');
        $data['priority'] ??= (int) $thisArt->getValue('priority');

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setWhere(['article_id' => $articleId, 'language_id' => $languageId])
            ->setValue('name', $data['name'])
            ->setValue('template', $data['template'])
            ->update();

        $EA = Sql::factory();
        $EA->setTable('rex_article');
        $EA->setWhere(['id' => $articleId]);
        $EA->setValue('priority', $data['priority']);
        $EA->addGlobalUpdateFields(self::getUser());
        $EA->update();

        $message = I18n::msg('article_updated');

        // ----- PRIOR
        $oldPrio = (int) $thisArt->getValue('priority');

        if ($oldPrio != $data['priority']) {
            self::newArtPrio($data['category_id'], $data['priority'], $oldPrio);
        }

        ArticleCache::delete($articleId);

        // ----- EXTENSION POINT
        $message = Extension::dispatch(new ExtensionPoint('ART_UPDATED', $message, [
            'id' => $articleId,
            'article' => self::select($articleId, $languageId),
            'article_old' => clone $thisArt,
            'status' => $thisArt->getValue('status'),
            'name' => $data['name'],
            'language' => $languageId,
            'parent_id' => $data['category_id'],
            'priority' => $data['priority'],
            'path' => $data['path'],
            'template_key' => $data['template'],
            'data' => $data,
        ]));

        return $message;
    }

    /**
     * Löscht einen Artikel und reorganisiert die Prioritäten verbleibender Geschwister-Artikel.
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function deleteArticle(int $articleId): string
    {
        $Art = Sql::factory();
        $Art->setQuery('SELECT a.* FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$articleId]);

        if (1 != $Art->getRows()) {
            throw new ApiFunctionException(I18n::msg('article_doesnt_exist'));
        }

        $message = self::_deleteArticle($articleId);
        $parentId = $Art->getNullableIntValue('parent_id');

        // ----- PRIOR
        self::newArtPrio($parentId, 0, 1);

        // ----- EXTENSION POINT
        $message = Extension::dispatch(new ExtensionPoint('ART_DELETED', $message, [
            'id' => $articleId,
            'parent_id' => $parentId,
            'priority' => $Art->getValue('priority'),
            'path' => $Art->getValue('path'),
        ]));

        return $message;
    }

    /**
     * Löscht einen Artikel.
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function _deleteArticle(int $id): string
    {
        // artikel loeschen

        // kontrolle ob erlaubnis nicht hier.. muss vorher geschehen

        // -> startarticle = 0
        // --> artikelfiles löschen
        // ---> article
        // ---> content
        // ---> clist
        // ---> alist
        // -> startarticle = 1
        // --> rekursiv aufrufen

        if ($id == Article::getSiteStartArticleId()) {
            throw new ApiFunctionException(I18n::msg('cant_delete_sitestartarticle'));
        }
        if ($id == Article::getNotfoundArticleId()) {
            throw new ApiFunctionException(I18n::msg('cant_delete_notfoundarticle'));
        }

        $ART = Sql::factory();
        $ART->setQuery('SELECT a.*, c.id IS NOT NULL AS startarticle FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$id]);

        $message = '';
        if ($ART->getRows() > 0) {
            $parentId = $ART->getNullableIntValue('parent_id');
            $message = Extension::dispatch(new ExtensionPoint('ART_PRE_DELETED', $message, [
                'id' => $id,
                'parent_id' => $parentId,
                'priority' => $ART->getValue('priority'),
                'path' => $ART->getValue('path'),
            ]));

            if (1 == $ART->getValue('startarticle')) {
                $message = I18n::msg('category_deleted');
                $SART = Sql::factory();
                $SART->setQuery('SELECT id FROM rex_article WHERE parent_id = ?', [$id]);
                foreach ($SART as $child) {
                    self::_deleteArticle((int) $child->getValue('id'));
                }
            } else {
                $message = I18n::msg('article_deleted');
            }

            ArticleCache::delete($id);
            // the translations and the category row are removed by the foreign keys
            $ART->setQuery('DELETE FROM rex_article WHERE id = ?', [$id]);
            $ART->setQuery('DELETE FROM rex_article_slice WHERE article_id = ?', [$id]);

            // --------------------------------------------------- Listen generieren
            ArticleCache::deleteLists($parentId);

            return $message;
        }
        throw new ApiFunctionException(I18n::msg('category_doesnt_exist'));
    }

    /**
     * Ändert den Status des Artikels.
     *
     * @param int|null $status Status auf den der Artikel gesetzt werden soll, oder NULL wenn zum nächsten Status weitergeschaltet werden soll
     *
     * @throws ApiFunctionException
     *
     * @return int Der neue Status des Artikels
     */
    public static function articleStatus(int $articleId, int $languageId, ?int $status = null): int
    {
        $GA = self::select($articleId, $languageId);
        if (1 == $GA->getRows()) {
            // Status wurde nicht von außen vorgegeben,
            // => zyklisch auf den nächsten Weiterschalten
            if (null === $status) {
                $newstatus = self::nextStatus((int) $GA->getValue('status'));
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

            // ----- EXTENSION POINT
            Extension::dispatch(new ExtensionPoint('ART_STATUS', null, [
                'id' => $articleId,
                'language' => $languageId,
                'status' => $newstatus,
            ]));
        } else {
            throw new ApiFunctionException(I18n::msg('no_such_category'));
        }

        return $newstatus;
    }

    /**
     * Gibt alle Stati zurück, die für einen Artikel gültig sind.
     *
     * @return list<array{string, string, string}> Array von Stati
     */
    public static function statusTypes(): array
    {
        /** @var list<array{string, string, string}> $artStatusTypes */
        static $artStatusTypes;

        if (!$artStatusTypes) {
            $artStatusTypes = [
                // Name, CSS-Class
                [I18n::msg('status_offline'), 'rex-offline', 'rex-icon-offline'],
                [I18n::msg('status_online'), 'rex-online', 'rex-icon-online'],
            ];

            // ----- EXTENSION POINT
            $artStatusTypes = Extension::dispatch(new ExtensionPoint('ART_STATUS_TYPES', $artStatusTypes));
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
     * @param int|null $parentId `null` for the root level
     */
    public static function newArtPrio(?int $parentId, int $newPrio, int $oldPrio): void
    {
        if ($newPrio != $oldPrio) {
            if ($newPrio < $oldPrio) {
                $addsql = 'desc';
            } else {
                $addsql = 'asc';
            }

            // the start article is listed among the articles of its own category
            $where = null === $parentId
                ? 'parent_id IS NULL AND id NOT IN (SELECT id FROM rex_category)'
                : '(parent_id = ' . $parentId . ' AND id NOT IN (SELECT id FROM rex_category)) OR id = ' . $parentId;

            Util::organizePriorities(
                'rex_article',
                'priority',
                $where,
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

    /** Konvertiert einen Artikel in eine Kategorie. */
    public static function article2category(int $artId): bool
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT a.parent_id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$artId]);
        if (1 != $sql->getRows()) {
            return false;
        }
        $parentId = $sql->getNullableIntValue('parent_id');

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

        CategoryHandler::newCatPrio($parentId, 1, 0);

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($artId);

        Extension::dispatch(new ExtensionPoint('ART_TO_CAT', '', ['id' => $artId]));

        return true;
    }

    /** Konvertiert eine Kategorie in einen Artikel. */
    public static function category2article(int $artId): bool
    {
        $sql = Sql::factory();

        // Kategorie muss leer sein
        $sql->setQuery('SELECT id FROM rex_article WHERE parent_id = ? LIMIT 1', [$artId]);
        if (0 != $sql->getRows()) {
            return false;
        }

        $sql->setQuery('SELECT a.parent_id FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$artId]);
        if (1 != $sql->getRows()) {
            return false;
        }
        $parentId = $sql->getNullableIntValue('parent_id');

        // the translations of the category are removed by the foreign key
        $sql->setQuery('DELETE FROM rex_category WHERE id = ?', [$artId]);

        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $artId])
            ->setValue('priority', 99999)
            ->addGlobalUpdateFields(self::getUser())
            ->update();

        self::newArtPrio($parentId, 1, 0);

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($artId);

        Extension::dispatch(new ExtensionPoint('CAT_TO_ART', '', ['id' => $artId]));

        return true;
    }

    /**
     * Makes an article the start article of its category.
     *
     * The category keeps its position, names and meta values, but continues under the id of the new start article,
     * while the previous start article becomes a plain article inside the category.
     */
    public static function article2startarticle(int $neuId): bool
    {
        $GAID = [];

        // neuen startartikel holen und schauen ob da
        $neu = Sql::factory();
        $neu->setQuery('SELECT a.* FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL', [$neuId]);
        if (1 != $neu->getRows()) {
            return false;
        }
        $altId = $neu->getNullableIntValue('parent_id');

        // in oberster kategorie dann return
        if (null === $altId) {
            return false;
        }

        // alten startartikel
        $alt = Sql::factory();
        $alt->setQuery('SELECT a.* FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$altId]);
        if (1 != $alt->getRows()) {
            return false;
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
            $iid = (int) $article->getValue('id');

            $ia->setTable('rex_article');
            $ia->setWhere(['id' => $iid]);
            $ia->setValue('path', str_replace("|$altId|", "|$neuId|", (string) $article->getValue('path')));
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

        Extension::dispatch(new ExtensionPoint('ART_TO_STARTARTICLE', '', [
            'id' => $neuId,
            'id_old' => $altId,
        ]));

        return true;
    }

    /**
     * Copies meta values from one article to another.
     *
     * Shared columns are copied once for all languages, translatable ones from `$fromLanguageId` to `$toLanguageId`.
     * Category columns are only copied if both articles are categories.
     *
     * @param list<string> $params Names of the columns to copy
     */
    public static function copyMeta(int $fromId, int $toId, int $fromLanguageId = 1, int $toLanguageId = 1, array $params = []): bool
    {
        if ($fromId === $toId && $fromLanguageId === $toLanguageId) {
            return false;
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
            return false;
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

        return true;
    }

    /**
     * Copies an article (or the start article of a category) as a plain article into another category.
     *
     * @param int|null $toCatId `null` for the root level
     *
     * @return int|false The id of the copied article, or `false` on failure
     */
    public static function copyArticle(int $id, ?int $toCatId): int|false
    {
        $user = self::getUser();

        $fromSql = Sql::factory();
        $fromSql->setQuery('SELECT * FROM rex_article WHERE id = ?', [$id]);
        if (1 != $fromSql->getRows()) {
            return false;
        }

        $path = '|';
        if (null !== $toCatId) {
            $toSql = Sql::factory();
            $toSql->setQuery('SELECT a.path FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$toCatId]);
            if (1 != $toSql->getRows()) {
                return false;
            }
            $path = $toSql->getValue('path') . $toCatId . '|';
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
            $translation->setValue('name', (string) $fromTranslation->getValue('name') . ' ' . I18n::msg('structure_copy'));
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
                ContentHandler::copyContent($id, $newId, $languageId, $languageId, (int) $rev->getValue('revision'));
            }
        }

        // Prios neu berechnen
        self::newArtPrio($toCatId, 1, 0);

        Extension::dispatch(new ExtensionPoint('ART_COPIED', null, [
            'id_source' => $id,
            'id' => $newId,
            'category_id' => $toCatId,
        ]));

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        if (null !== $toCatId) {
            ArticleCache::delete($toCatId);
        }

        return $newId;
    }

    /**
     * Moves an article into another category.
     *
     * @param int|null $fromCatId `null` for the root level
     * @param int|null $toCatId `null` for the root level
     */
    public static function moveArticle(int $id, ?int $fromCatId, ?int $toCatId): bool
    {
        if ($fromCatId === $toCatId) {
            return false;
        }

        // validierung der id & from_cat_id
        $fromSql = Sql::factory();
        $fromSql->setQuery('SELECT a.id FROM rex_article a LEFT JOIN rex_category c ON c.id = a.id WHERE a.id = ? AND c.id IS NULL AND a.parent_id <=> ?', [$id, $fromCatId]);
        if (1 != $fromSql->getRows()) {
            return false;
        }

        // validierung der to_cat_id
        $path = '|';
        if (null !== $toCatId) {
            $toSql = Sql::factory();
            $toSql->setQuery('SELECT a.path FROM rex_article a JOIN rex_category c ON c.id = a.id WHERE a.id = ?', [$toCatId]);
            if (1 != $toSql->getRows()) {
                return false;
            }
            $path = $toSql->getValue('path') . $toCatId . '|';
        }

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
        self::newArtPrio($toCatId, 1, 0);
        self::newArtPrio($fromCatId, 1, 0);

        Extension::dispatch(new ExtensionPoint('ART_MOVED', null, [
            'id' => $id,
            'category_id' => $toCatId,
        ]));

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        foreach ([$fromCatId, $toCatId] as $categoryId) {
            if (null !== $categoryId) {
                ArticleCache::delete($categoryId);
            }
        }

        return true;
    }

    /** Selects the article together with its translation in the given language. */
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

    /**
     * Checks whether the required array key $keyName isset.
     *
     * @param array<string, mixed> $array
     *
     * @throws ApiFunctionException
     */
    private static function reqKey(array $array, string $keyName): void
    {
        if (!array_key_exists($keyName, $array)) {
            throw new ApiFunctionException('Missing required parameter "' . $keyName . '"!');
        }
    }

    private static function getUser(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
