<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\ComplexPermission;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function count;
use function is_array;

final class ArticleHandler
{
    private function __construct() {}

    /**
     * Erstellt einen neuen Artikel.
     *
     * @param array $data Array mit den Daten des Artikels
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function addArticle(array $data): string
    {
        self::reqKey($data, 'category_id');
        self::reqKey($data, 'priority');
        self::reqKey($data, 'name');

        if ($data['priority'] <= 0) {
            $data['priority'] = 1;
        }

        if ($data['category_id']) {
            $parent = Category::get($data['category_id']);
            if (!$parent) {
                throw new ApiFunctionException('Target category with ID "' . $data['category_id'] . '" does not exist.');
            }
            $path = '|' . implode('|', [...$parent->path, $parent->id]) . '|';
        } else {
            $path = '|';
        }

        $templates = Template::getTemplatesForCategory($data['category_id']);
        $templateKey = isset($data['template']) ? Type::string($data['template']) : null;

        // Wenn Template nicht vorhanden, dann entweder erlaubtes nehmen
        // oder leer setzen.
        if (null === $templateKey || !isset($templates[$templateKey])) {
            $templateKey = array_key_first($templates);
        }
        $data['template'] = $templateKey;

        $message = I18n::msg('article_added');

        $AART = Sql::factory();
        $user = self::getUser();
        foreach (Language::getAllIds() as $key) {
            // ------- Kategorienamen holen
            $category = Category::get($data['category_id'], $key);

            $categoryName = '';
            if ($category) {
                $categoryName = $category->name;
            }

            $AART->setTable(Core::getTablePrefix() . 'article');
            if (!isset($id) || !$id) {
                $id = $AART->setNewId('id');
            } else {
                $AART->setValue('id', $id);
            }
            $AART->setValue('name', $data['name']);
            $AART->setValue('catname', $categoryName);
            $AART->setValue('catpriority', 0);
            $AART->setValue('clang_id', $key);
            $AART->setValue('parent_id', $data['category_id']);
            $AART->setValue('priority', $data['priority']);
            $AART->setValue('path', $path);
            $AART->setValue('startarticle', 0);
            $AART->setValue('status', 0);
            $AART->setValue('template', $data['template']);
            $AART->addGlobalCreateFields($user);
            $AART->addGlobalUpdateFields($user);

            $AART->insert();
            // ----- PRIOR
            self::newArtPrio($data['category_id'], $key, 0, $data['priority']);

            ArticleCache::delete($id, $key);

            // ----- EXTENSION POINT
            $message = Extension::dispatch(new ExtensionPoint('ART_ADDED', $message, [
                'id' => $id,
                'clang' => $key,
                'status' => 0,
                'name' => $data['name'],
                'parent_id' => $data['category_id'],
                'priority' => $data['priority'],
                'path' => $path,
                'template_key' => $data['template'],
                'data' => $data,
            ]));
        }

        return $message;
    }

    /**
     * Bearbeitet einen Artikel.
     *
     * @param array $data Array mit den Daten des Artikels
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function editArticle(int $articleId, int $clang, array $data): string
    {
        self::reqKey($data, 'name');

        // Artikel mit alten Daten selektieren
        $thisArt = Sql::factory();
        $thisArt->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and clang_id=?', [$articleId, $clang]);

        if (1 != $thisArt->getRows()) {
            throw new ApiFunctionException('Unable to find article with id "' . $articleId . '" and clang "' . $clang . '"!');
        }

        $ooArt = Article::require($articleId, $clang);
        $data['category_id'] = $ooArt->categoryId;

        $templates = Template::getTemplatesForCategory($data['category_id']);
        $templateKey = isset($data['template']) ? Type::string($data['template']) : null;

        // Wenn Template nicht vorhanden, dann entweder erlaubtes nehmen
        // oder leer setzen.
        if (null === $templateKey || !isset($templates[$templateKey])) {
            $templateKey = array_key_first($templates);
        }
        $data['template'] = $templateKey;

        if (isset($data['priority'])) {
            if ($data['priority'] <= 0) {
                $data['priority'] = 1;
            }
        }

        // complete remaining optional aprams
        $data['path'] ??= $thisArt->getValue('path');
        $data['priority'] ??= $thisArt->getValue('priority');

        $EA = Sql::factory();
        $EA->setTable(Core::getTablePrefix() . 'article');
        $EA->setWhere(['id' => $articleId, 'clang_id' => $clang]);
        $EA->setValue('name', $data['name']);
        $EA->setValue('template', $data['template']);
        $EA->setValue('priority', $data['priority']);
        $EA->addGlobalUpdateFields(self::getUser());

        $EA->update();
        $message = I18n::msg('article_updated');

        // ----- PRIOR
        $oldPrio = (int) $thisArt->getValue('priority');

        if ($oldPrio != $data['priority']) {
            Sql::factory()
                ->setTable(Core::getTable('article'))
                ->setWhere('id = :id AND clang_id != :clang', ['id' => $articleId, 'clang' => $clang])
                ->setValue('priority', $data['priority'])
                ->addGlobalUpdateFields(self::getUser())
                ->update();

            foreach (Language::getAllIds() as $clangId) {
                self::newArtPrio($data['category_id'], $clangId, $data['priority'], $oldPrio);
            }
        }

        ArticleCache::delete($articleId);

        // ----- EXTENSION POINT
        $message = Extension::dispatch(new ExtensionPoint('ART_UPDATED', $message, [
            'id' => $articleId,
            'article' => clone $EA,
            'article_old' => clone $thisArt,
            'status' => $thisArt->getValue('status'),
            'name' => $data['name'],
            'clang' => $clang,
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
        $Art->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and startarticle=0', [$articleId]);

        if ($Art->getRows() > 0) {
            $message = self::_deleteArticle($articleId);
            $parentId = (int) $Art->getValue('parent_id');

            foreach (Language::getAllIds() as $clang) {
                // ----- PRIOR
                self::newArtPrio($parentId, $clang, 0, 1);

                // ----- EXTENSION POINT
                $message = Extension::dispatch(new ExtensionPoint('ART_DELETED', $message, [
                    'id' => $articleId,
                    'clang' => $clang,
                    'parent_id' => $parentId,
                    'name' => $Art->getValue('name'),
                    'status' => $Art->getValue('status'),
                    'priority' => $Art->getValue('priority'),
                    'path' => $Art->getValue('path'),
                    'template_key' => $Art->getValue('template'),
                ]));

                $Art->next();
            }
        } else {
            throw new ApiFunctionException(I18n::msg('article_doesnt_exist'));
        }

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
        $ART->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and clang_id=?', [$id, Language::getStartId()]);

        $message = '';
        if ($ART->getRows() > 0) {
            $parentId = (int) $ART->getValue('parent_id');
            $message = Extension::dispatch(new ExtensionPoint('ART_PRE_DELETED', $message, [
                'id' => $id,
                'parent_id' => $parentId,
                'name' => $ART->getValue('name'),
                'status' => $ART->getValue('status'),
                'priority' => $ART->getValue('priority'),
                'path' => $ART->getValue('path'),
                'template_key' => $ART->getValue('template'),
            ]));

            if (1 == $ART->getValue('startarticle')) {
                $message = I18n::msg('category_deleted');
                $SART = Sql::factory();
                $SART->setQuery('select * from ' . Core::getTablePrefix() . 'article where parent_id=? and clang_id=?', [$id, Language::getStartId()]);
                for ($i = 0; $i < $SART->getRows(); ++$i) {
                    self::_deleteArticle($id);
                    $SART->next();
                }
            } else {
                $message = I18n::msg('article_deleted');
            }

            ArticleCache::delete($id);
            $ART->setQuery('delete from ' . Core::getTablePrefix() . 'article where id=?', [$id]);
            $ART->setQuery('delete from ' . Core::getTablePrefix() . 'article_slice where article_id=?', [$id]);

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
    public static function articleStatus(int $articleId, int $clang, ?int $status = null): int
    {
        $GA = Sql::factory();
        $GA->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and clang_id=?', [$articleId, $clang]);
        if (1 == $GA->getRows()) {
            // Status wurde nicht von außen vorgegeben,
            // => zyklisch auf den nächsten Weiterschalten
            if (null === $status) {
                $newstatus = self::nextStatus($GA->getValue('status'));
            } else {
                $newstatus = $status;
            }

            $EA = Sql::factory();
            $EA->setTable(Core::getTablePrefix() . 'article');
            $EA->setWhere(['id' => $articleId, 'clang_id' => $clang]);
            $EA->setValue('status', $newstatus);
            $EA->addGlobalUpdateFields(self::getUser());

            $EA->update();

            ArticleCache::delete($articleId, $clang);

            // ----- EXTENSION POINT
            Extension::dispatch(new ExtensionPoint('ART_STATUS', null, [
                'id' => $articleId,
                'clang' => $clang,
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

    public static function nextStatus($currentStatus): int
    {
        $artStatusTypes = self::statusTypes();
        return ($currentStatus + 1) % count($artStatusTypes);
    }

    public static function prevStatus($currentStatus): int
    {
        $artStatusTypes = self::statusTypes();
        if (($currentStatus - 1) < 0) {
            return count($artStatusTypes) - 1;
        }

        return ($currentStatus - 1) % count($artStatusTypes);
    }

    /**
     * Berechnet die Prios der Artikel in einer Kategorie neu.
     *
     * @param int|null $parentId KategorieId der Kategorie, die erneuert werden soll
     * @param int $clang ClangId der Kategorie, die erneuert werden soll
     * @param int $newPrio Neue PrioNr der Kategorie
     * @param int $oldPrio Alte PrioNr der Kategorie
     */
    public static function newArtPrio($parentId, $clang, $newPrio, $oldPrio): void
    {
        $parentId = (int) $parentId;

        if ($newPrio != $oldPrio) {
            if ($newPrio < $oldPrio) {
                $addsql = 'desc';
            } else {
                $addsql = 'asc';
            }

            Util::organizePriorities(
                Core::getTable('article'),
                'priority',
                'clang_id=' . (int) $clang . ' AND ((startarticle<>1 AND parent_id=' . $parentId . ') OR (startarticle=1 AND id=' . $parentId . '))',
                'priority,updatedate ' . $addsql,
            );

            ArticleCache::deleteLists($parentId);
            ArticleCache::deleteMeta($parentId);

            $ids = Sql::factory()->getArray('SELECT id FROM ' . Core::getTable('article') . ' WHERE startarticle=0 AND parent_id = ? GROUP BY id', [$parentId]);
            foreach ($ids as $id) {
                ArticleCache::deleteMeta((int) $id['id']);
            }
        }
    }

    /** Konvertiert einen Artikel in eine Kategorie. */
    public static function article2category(int $artId): bool
    {
        $sql = Sql::factory();
        $parentId = 0;

        // LANG SCHLEIFE
        foreach (Language::getAllIds() as $clang) {
            // artikel
            $sql->setQuery('select parent_id, name from ' . Core::getTablePrefix() . 'article where id=? and startarticle=0 and clang_id=?', [$artId, $clang]);

            if (!$parentId) {
                $parentId = (int) $sql->getValue('parent_id');
            }

            // artikel updaten
            $sql->setTable(Core::getTablePrefix() . 'article');
            $sql->setWhere(['id' => $artId, 'clang_id' => $clang]);
            $sql->setValue('startarticle', 1);
            $sql->setValue('catname', $sql->getValue('name'));
            $sql->setValue('catpriority', 99999);
            $sql->setValue('priority', 1);
            $sql->update();

            CategoryHandler::newCatPrio($parentId, $clang, 1, 0);
        }

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($artId);

        foreach (Language::getAllIds() as $clang) {
            Extension::dispatch(new ExtensionPoint('ART_TO_CAT', '', [
                'id' => $artId,
                'clang' => $clang,
            ]));
        }

        return true;
    }

    /** Konvertiert eine Kategorie in einen Artikel. */
    public static function category2article(int $artId): bool
    {
        $sql = Sql::factory();
        $parentId = 0;

        // Kategorie muss leer sein
        $sql->setQuery('SELECT pid FROM ' . Core::getTablePrefix() . 'article WHERE parent_id=? LIMIT 1', [$artId]);
        if (0 != $sql->getRows()) {
            return false;
        }

        // LANG SCHLEIFE
        foreach (Language::getAllIds() as $clang) {
            // artikel
            $sql->setQuery('
                select parent_id, (select catname FROM ' . Core::getTable('article') . ' parent WHERE parent.id = category.parent_id AND parent.clang_id = category.clang_id) as catname
                from ' . Core::getTable('article') . ' category
                where id=? and startarticle=1 and clang_id=?
            ', [$artId, $clang]);

            if (!$parentId) {
                $parentId = (int) $sql->getValue('parent_id');
            }

            $catname = (string) $sql->getValue('catname');

            // artikel updaten
            $sql->setTable(Core::getTablePrefix() . 'article');
            $sql->setWhere(['id' => $artId, 'clang_id' => $clang]);
            $sql->setValue('startarticle', 0);
            $sql->setValue('catname', $catname);
            $sql->setValue('priority', 99999);
            $sql->setValue('catpriority', 0);
            $sql->update();

            self::newArtPrio($parentId, $clang, 1, 0);
        }

        ArticleCache::deleteLists($parentId);
        ArticleCache::delete($artId);

        foreach (Language::getAllIds() as $clang) {
            Extension::dispatch(new ExtensionPoint('CAT_TO_ART', '', [
                'id' => $artId,
                'clang' => $clang,
            ]));
        }

        return true;
    }

    /** Konvertiert einen Artikel zum Startartikel der eigenen Kategorie. */
    public static function article2startarticle(int $neuId): bool
    {
        $GAID = [];

        // neuen startartikel holen und schauen ob da
        $neu = Sql::factory();
        $neu->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and startarticle=0 and clang_id=?', [$neuId, Language::getStartId()]);
        if (1 != $neu->getRows()) {
            return false;
        }
        $neuCatId = (int) $neu->getValue('parent_id');

        // in oberster kategorie dann return
        if (0 == $neuCatId) {
            return false;
        }

        // alten startartikel
        $alt = Sql::factory();
        $alt->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and startarticle=1 and clang_id=?', [$neuCatId, Language::getStartId()]);
        if (1 != $alt->getRows()) {
            return false;
        }
        $altId = (int) $alt->getValue('id');
        $parentId = (int) $alt->getValue('parent_id');

        // cat felder sammeln. +
        $params = ['path', 'priority', 'catname', 'startarticle', 'catpriority', 'status'];
        foreach ($alt->getFieldnames() as $field) {
            if (str_starts_with($field, 'cat_')) {
                $params[] = $field;
            }
        }

        // LANG SCHLEIFE
        foreach (Language::getAllIds() as $clang) {
            // alter startartikel
            $alt->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and startarticle=1 and clang_id=?', [$neuCatId, $clang]);

            // neuer startartikel
            $neu->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and startarticle=0 and clang_id=?', [$neuId, $clang]);

            // alter startartikel updaten
            $alt2 = Sql::factory();
            $alt2->setTable(Core::getTablePrefix() . 'article');
            $alt2->setWhere(['id' => $altId, 'clang_id' => $clang]);
            $alt2->setValue('parent_id', $neuId);

            // neuer startartikel updaten
            $neu2 = Sql::factory();
            $neu2->setTable(Core::getTablePrefix() . 'article');
            $neu2->setWhere(['id' => $neuId, 'clang_id' => $clang]);
            $neu2->setValue('parent_id', (int) $alt->getValue('parent_id'));

            // austauschen der definierten paramater
            foreach ($params as $param) {
                $alt2->setValue($param, $neu->getValue($param));
                $neu2->setValue($param, $alt->getValue($param));
            }
            $alt2->update();
            $neu2->update();
        }

        // alle artikel suchen nach |art_id| und pfade ersetzen
        // alles artikel mit parent_id alt_id suchen und ersetzen

        $articles = Sql::factory();
        $ia = Sql::factory();
        $articles->setQuery('select * from ' . Core::getTablePrefix() . "article where path like '%|$altId|%'");
        for ($i = 0; $i < $articles->getRows(); ++$i) {
            $iid = (int) $articles->getValue('id');
            $ipath = str_replace("|$altId|", "|$neuId|", (string) $articles->getValue('path'));

            $ia->setTable(Core::getTablePrefix() . 'article');
            $ia->setWhere(['id' => $iid]);
            $ia->setValue('path', $ipath);
            if ($articles->getValue('parent_id') == $altId) {
                $ia->setValue('parent_id', $neuId);
            }
            $ia->update();
            $GAID[$iid] = $iid;
            $articles->next();
        }

        $GAID[$neuId] = $neuId;
        $GAID[$altId] = $altId;
        $GAID[$parentId] = $parentId;

        foreach ($GAID as $gid) {
            ArticleCache::delete($gid);
        }

        ComplexPermission::replaceItem('structure', $altId, $neuId);

        foreach (Language::getAllIds() as $clang) {
            Extension::dispatch(new ExtensionPoint('ART_TO_STARTARTICLE', '', [
                'id' => $neuId,
                'id_old' => $altId,
                'clang' => $clang,
            ]));
        }

        return true;
    }

    /**
     * Kopiert die Metadaten eines Artikels in einen anderen Artikel.
     *
     * @param int $fromId ArtikelId des Artikels, aus dem kopiert werden (Quell ArtikelId)
     * @param int $toId ArtikelId des Artikel, in den kopiert werden sollen (Ziel ArtikelId)
     * @param int $fromClang ClangId des Artikels, aus dem kopiert werden soll (Quell ClangId)
     * @param int $toClang ClangId des Artikels, in den kopiert werden soll (Ziel ClangId)
     * @param array $params Array von Spaltennamen, welche kopiert werden sollen
     */
    public static function copyMeta($fromId, $toId, $fromClang = 1, $toClang = 1, $params = []): bool
    {
        $fromClang = (int) $fromClang;
        $toClang = (int) $toClang;
        $fromId = (int) $fromId;
        $toId = (int) $toId;
        if (!is_array($params)) {
            $params = [];
        }

        if ($fromId == $toId && $fromClang == $toClang) {
            return false;
        }

        $gc = Sql::factory();
        $gc->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=? and id=?', [$fromClang, $fromId]);

        if (1 == $gc->getRows()) {
            $uc = Sql::factory();
            // $uc->setDebug();
            $uc->setTable(Core::getTablePrefix() . 'article');
            $uc->setWhere(['clang_id' => $toClang, 'id' => $toId]);
            $uc->addGlobalUpdateFields(self::getUser());

            foreach ($params as $value) {
                $uc->setValue($value, $gc->getValue($value));
            }

            $uc->update();

            ArticleCache::deleteMeta($toId, $toClang);
            return true;
        }
        return false;
    }

    /**
     * Kopieren eines Artikels von einer Kategorie in eine andere.
     *
     * @param int $id ArtikelId des zu kopierenden Artikels
     * @param int $toCatId KategorieId in die der Artikel kopiert werden soll
     *
     * @return bool|int FALSE bei Fehler, sonst die Artikel Id des neue kopierten Artikels
     */
    public static function copyArticle($id, $toCatId): bool|int
    {
        $id = (int) $id;
        $toCatId = (int) $toCatId;
        $newId = false;

        $user = self::getUser();

        // Artikel in jeder Sprache kopieren
        foreach (Language::getAllIds() as $clang) {
            // validierung der id & from_cat_id
            $fromSql = Sql::factory();
            $fromSql->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=? and id=?', [$clang, $id]);

            if (1 == $fromSql->getRows()) {
                // validierung der to_cat_id
                $toSql = Sql::factory();
                $toSql->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=? and startarticle=1 and id=?', [$clang, $toCatId]);

                if (1 == $toSql->getRows() || 0 == $toCatId) {
                    if (1 == $toSql->getRows()) {
                        $path = $toSql->getValue('path') . $toSql->getValue('id') . '|';
                        $catname = $toSql->getValue('catname');
                    } else {
                        // In RootEbene
                        $path = '|';
                        $catname = $fromSql->getValue('name');
                    }

                    $artSql = Sql::factory();
                    $artSql->setTable(Core::getTablePrefix() . 'article');
                    if (false === $newId) {
                        $newId = $artSql->setNewId('id');
                    }
                    $artSql->setValue('id', $newId); // neuen auto_incrment erzwingen
                    $artSql->setValue('parent_id', $toCatId);
                    $artSql->setValue('catname', $catname);
                    $artSql->setValue('catpriority', 0);
                    $artSql->setValue('path', $path);
                    $artSql->setValue('name', $fromSql->getValue('name') . ' ' . I18n::msg('structure_copy'));
                    $artSql->setValue('priority', 99_999); // Artikel als letzten Artikel in die neue Kat einfügen
                    $artSql->setValue('status', 0); // Kopierter Artikel offline setzen
                    $artSql->setValue('startarticle', 0);
                    $artSql->addGlobalUpdateFields($user);
                    $artSql->addGlobalCreateFields($user);

                    // schon gesetzte Felder nicht wieder überschreiben
                    $dontCopy = ['id', 'pid', 'parent_id', 'catname', 'name', 'catpriority', 'path', 'priority', 'status', 'updatedate', 'updateuser', 'createdate', 'createuser', 'startarticle'];

                    foreach (array_diff($fromSql->getFieldnames(), $dontCopy) as $fldName) {
                        $artSql->setValue($fldName, $fromSql->getValue($fldName));
                    }

                    $artSql->setValue('clang_id', $clang);
                    $artSql->insert();

                    $revisions = Sql::factory();
                    $revisions->setQuery('select revision from ' . Core::getTablePrefix() . 'article_slice where priority=1 AND article_id=? AND clang_id=? GROUP BY revision', [$id, $clang]);
                    foreach ($revisions as $rev) {
                        // FIXME this dependency is very ugly!
                        // ArticleSlices kopieren
                        ContentHandler::copyContent($id, $newId, $clang, $clang, (int) $rev->getValue('revision'));
                    }

                    // Prios neu berechnen
                    self::newArtPrio($toCatId, $clang, 1, 0);

                    Extension::dispatch(new ExtensionPoint('ART_COPIED', null, [
                        'id_source' => $id,
                        'id' => $newId,
                        'clang' => $clang,
                        'category_id' => $toCatId,
                    ]));
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        ArticleCache::delete($toCatId);

        return $newId;
    }

    /**
     * Verschieben eines Artikels von einer Kategorie in eine Andere.
     *
     * @param int $id ArtikelId des zu verschiebenden Artikels
     * @param int $fromCatId KategorieId des Artikels, der Verschoben wird
     * @param int $toCatId KategorieId in die der Artikel verschoben werden soll
     */
    public static function moveArticle($id, $fromCatId, $toCatId): bool
    {
        $id = (int) $id;
        $toCatId = (int) $toCatId;
        $fromCatId = (int) $fromCatId;

        if ($fromCatId == $toCatId) {
            return false;
        }

        // Artikel in jeder Sprache verschieben
        foreach (Language::getAllIds() as $clang) {
            // validierung der id & from_cat_id
            $fromSql = Sql::factory();
            $fromSql->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=? and startarticle<>1 and id=? and parent_id=?', [$clang, $id, $fromCatId]);

            if (1 == $fromSql->getRows()) {
                // validierung der to_cat_id
                $toSql = Sql::factory();
                $toSql->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=? and startarticle=1 and id=?', [$clang, $toCatId]);

                if (1 == $toSql->getRows() || 0 == $toCatId) {
                    if (1 == $toSql->getRows()) {
                        $parentId = $toSql->getValue('id');
                        $path = $toSql->getValue('path') . $toSql->getValue('id') . '|';
                        $catname = $toSql->getValue('catname');
                    } else {
                        // In RootEbene
                        $parentId = 0;
                        $path = '|';
                        $catname = $fromSql->getValue('name');
                    }

                    $artSql = Sql::factory();
                    // $art_sql->setDebug();

                    $artSql->setTable(Core::getTablePrefix() . 'article');
                    $artSql->setValue('parent_id', $parentId);
                    $artSql->setValue('path', $path);
                    $artSql->setValue('catname', $catname);
                    // Artikel als letzten Artikel in die neue Kat einfügen
                    $artSql->setValue('priority', 99999);
                    // Kopierter Artikel offline setzen
                    $artSql->setValue('status', $fromSql->getValue('status'));
                    $artSql->addGlobalUpdateFields(self::getUser());

                    $artSql->setWhere('clang_id="' . $clang . '" and startarticle<>1 and id="' . $id . '" and parent_id="' . $fromCatId . '"');
                    $artSql->update();

                    // Prios neu berechnen
                    self::newArtPrio($toCatId, $clang, 1, 0);
                    self::newArtPrio($fromCatId, $clang, 1, 0);

                    Extension::dispatch(new ExtensionPoint('ART_MOVED', null, [
                        'id' => $id,
                        'clang' => $clang,
                        'category_id' => $parentId,
                    ]));
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Caches des Artikels löschen, in allen Sprachen
        ArticleCache::delete($id);

        // Caches der Kategorien löschen, da sich derin befindliche Artikel geändert haben
        ArticleCache::delete($fromCatId);
        ArticleCache::delete($toCatId);

        return true;
    }

    /**
     * Checks whether the required array key $keyName isset.
     *
     * @throws ApiFunctionException
     */
    private static function reqKey(array $array, string $keyName): void
    {
        if (!isset($array[$keyName])) {
            throw new ApiFunctionException('Missing required parameter "' . $keyName . '"!');
        }
    }

    private static function getUser(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
