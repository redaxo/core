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

use function count;
use function in_array;

/**
 * Funktionensammlung für die Strukturverwaltung.
 */
final class CategoryHandler
{
    private function __construct() {}

    /**
     * Erstellt eine neue Kategorie.
     *
     * @param int $categoryId KategorieId in der die neue Kategorie erstellt werden soll
     * @param array $data Array mit den Daten der Kategorie
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function addCategory(int $categoryId, array $data): string
    {
        $message = '';

        self::reqKey($data, 'catpriority');
        self::reqKey($data, 'catname');

        if ($categoryId) {
            $parent = Category::get($categoryId);
            if (!$parent) {
                throw new ApiFunctionException('Target category with ID "' . $categoryId . '" does not exist.');
            }
            $path = '|' . implode('|', [...$parent->path, $parent->id]) . '|';
        } else {
            $path = '|';
        }

        if ($data['catpriority'] <= 0) {
            $data['catpriority'] = 1;
        }

        if (!isset($data['name'])) {
            $data['name'] = $data['catname'];
        }

        if (!isset($data['status'])) {
            $data['status'] = 0;
        }

        $startpageTemplates = [];
        if ('' != $categoryId) {
            // TemplateId vom Startartikel der jeweiligen Sprache vererben
            $sql = Sql::factory();
            // $sql->setDebug();
            $sql->setQuery('select clang_id,template from ' . Core::getTablePrefix() . 'article where id=? and startarticle=1', [$categoryId]);
            for ($i = 0; $i < $sql->getRows(); $i++, $sql->next()) {
                $template = (string) $sql->getValue('template');
                if ('' !== $template) {
                    $startpageTemplates[(int) $sql->getValue('clang_id')] = $template;
                }
            }
        }

        // Alle Templates der Kategorie
        $templates = Template::getTemplatesForCategory($categoryId);

        $user = self::getUser();

        // Kategorie in allen Sprachen anlegen
        $AART = Sql::factory();
        foreach (Language::getAllIds() as $key) {
            // Inherit the template from the start article of the respective language, otherwise use the default
            $templateKey = $startpageTemplates[$key] ?? Template::getDefaultKey();

            // Fall back to the first allowed template if the chosen one is not available in this category
            if (null === $templateKey || !isset($templates[$templateKey])) {
                $templateKey = array_key_first($templates);
            }

            $AART->setTable(Core::getTablePrefix() . 'article');
            if (!isset($id)) {
                $id = $AART->setNewId('id');
            } else {
                $AART->setValue('id', $id);
            }

            $AART->setValue('clang_id', $key);
            $AART->setValue('template', $templateKey);
            $AART->setValue('name', $data['name']);
            $AART->setValue('catname', $data['catname']);
            $AART->setValue('catpriority', $data['catpriority']);
            $AART->setValue('parent_id', $categoryId);
            $AART->setValue('priority', 1);
            $AART->setValue('path', $path);
            $AART->setValue('startarticle', 1);
            $AART->setValue('status', $data['status']);
            $AART->addGlobalUpdateFields($user);
            $AART->addGlobalCreateFields($user);

            $AART->insert();

            // ----- PRIOR
            if (isset($data['catpriority'])) {
                self::newCatPrio($categoryId, $key, 0, $data['catpriority']);
            }

            $message = I18n::msg('category_added_and_startarticle_created');

            ArticleCache::delete($id, $key);

            // ----- EXTENSION POINT
            // Objekte clonen, damit diese nicht von der extension veraendert werden koennen
            $message = Extension::dispatch(new ExtensionPoint('CAT_ADDED', $message, [
                'category' => clone $AART,
                'id' => $id,
                'parent_id' => $categoryId,
                'clang' => $key,
                'name' => $data['catname'],
                'priority' => $data['catpriority'],
                'path' => $path,
                'status' => $data['status'],
                'article' => clone $AART,
                'data' => $data,
            ]));
        }

        return $message;
    }

    /**
     * Bearbeitet einer Kategorie.
     *
     * @param array $data Array mit den Daten der Kategorie
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function editCategory(int $categoryId, int $clang, array $data): string
    {
        // --- Kategorie mit alten Daten selektieren
        $thisCat = Sql::factory();
        $thisCat->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE startarticle=1 and id=? and clang_id=?', [$categoryId, $clang]);

        // --- Kategorie selbst updaten
        $EKAT = Sql::factory();
        $EKAT->setTable(Core::getTablePrefix() . 'article');
        $EKAT->setWhere(['id' => $categoryId, 'startarticle' => 1, 'clang_id' => $clang]);

        if (isset($data['catname'])) {
            $EKAT->setValue('catname', $data['catname']);
        }
        if (isset($data['catpriority'])) {
            $EKAT->setValue('catpriority', $data['catpriority']);
        }

        $user = self::getUser();

        $EKAT->addGlobalUpdateFields($user);

        $EKAT->update();

        // --- Kategorie Kindelemente updaten
        if (isset($data['catname'])) {
            $ArtSql = Sql::factory();
            $ArtSql->setQuery('SELECT id FROM ' . Core::getTablePrefix() . 'article WHERE parent_id=? AND startarticle=0 AND clang_id=?', [$categoryId, $clang]);

            $EART = Sql::factory();
            for ($i = 0; $i < $ArtSql->getRows(); ++$i) {
                $EART->setTable(Core::getTablePrefix() . 'article');
                $EART->setWhere(['id' => $ArtSql->getValue('id'), 'startarticle' => '0', 'clang_id' => $clang]);
                $EART->setValue('catname', $data['catname']);
                $EART->addGlobalUpdateFields($user);

                $EART->update();
                ArticleCache::delete((int) $ArtSql->getValue('id'), $clang);

                $ArtSql->next();
            }
        }

        // ----- PRIOR
        if (isset($data['catpriority'])) {
            $parentId = (int) $thisCat->getValue('parent_id');
            $oldPrio = (int) $thisCat->getValue('catpriority');

            if ($data['catpriority'] <= 0) {
                $data['catpriority'] = 1;
            }

            if ($oldPrio != $data['catpriority']) {
                Sql::factory()
                    ->setTable(Core::getTable('article'))
                    ->setWhere('id = :id AND clang_id != :clang', ['id' => $categoryId, 'clang' => $clang])
                    ->setValue('catpriority', $data['catpriority'])
                    ->addGlobalUpdateFields($user)
                    ->update();

                foreach (Language::getAllIds() as $clangId) {
                    self::newCatPrio($parentId, $clangId, $data['catpriority'], $oldPrio);
                }
            }
        }

        $message = I18n::msg('category_updated');

        ArticleCache::delete($categoryId);

        // ----- EXTENSION POINT
        // Objekte clonen, damit diese nicht von der extension veraendert werden koennen
        $message = Extension::dispatch(new ExtensionPoint('CAT_UPDATED', $message, [
            'id' => $categoryId,

            'category' => clone $EKAT,
            'category_old' => clone $thisCat,
            'article' => clone $EKAT,

            'parent_id' => $thisCat->getValue('parent_id'),
            'clang' => $clang,
            'name' => $data['catname'] ?? $thisCat->getValue('catname'),
            'priority' => $data['catpriority'] ?? $thisCat->getValue('catpriority'),
            'path' => $thisCat->getValue('path'),
            'status' => $thisCat->getValue('status'),

            'data' => $data,
        ]));

        return $message;
    }

    /**
     * Löscht eine Kategorie und reorganisiert die Prioritäten verbleibender Geschwister-Kategorien.
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function deleteCategory(int $categoryId): string
    {
        $clang = Language::getStartId();

        $thisCat = Sql::factory();
        $thisCat->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE id=? and clang_id=?', [$categoryId, $clang]);

        // Prüfen ob die Kategorie existiert
        if (1 == $thisCat->getRows()) {
            $KAT = Sql::factory();
            $KAT->setQuery('select * from ' . Core::getTablePrefix() . 'article where parent_id=? and clang_id=? and startarticle=1', [$categoryId, $clang]);
            // Prüfen ob die Kategorie noch Unterkategorien besitzt
            if (0 == $KAT->getRows()) {
                $KAT->setQuery('select * from ' . Core::getTablePrefix() . 'article where parent_id=? and clang_id=? and startarticle=0', [$categoryId, $clang]);
                // Prüfen ob die Kategorie noch Artikel besitzt (ausser dem Startartikel)
                if (0 == $KAT->getRows()) {
                    $thisCat = Sql::factory();
                    $thisCat->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE id=?', [$categoryId]);

                    $parentId = (int) $thisCat->getValue('parent_id');
                    $message = ArticleHandler::_deleteArticle($categoryId);

                    foreach ($thisCat as $row) {
                        $clang = (int) $row->getValue('clang_id');

                        // ----- PRIOR
                        self::newCatPrio($parentId, $clang, 0, 1);

                        // ----- EXTENSION POINT
                        $message = Extension::dispatch(new ExtensionPoint('CAT_DELETED', $message, [
                            'id' => $categoryId,
                            'parent_id' => $parentId,
                            'clang' => $clang,
                            'name' => $row->getValue('catname'),
                            'priority' => $row->getValue('catpriority'),
                            'path' => $row->getValue('path'),
                            'status' => $row->getValue('status'),
                        ]));
                    }

                    ComplexPermission::removeItem('structure', $categoryId);
                } else {
                    throw new ApiFunctionException(I18n::msg('category_could_not_be_deleted') . ' ' . I18n::msg('category_still_contains_articles'));
                }
            } else {
                throw new ApiFunctionException(I18n::msg('category_could_not_be_deleted') . ' ' . I18n::msg('category_still_contains_subcategories'));
            }
        } else {
            throw new ApiFunctionException(I18n::msg('category_could_not_be_deleted'));
        }

        return $message;
    }

    /**
     * Ändert den Status der Kategorie.
     *
     * @param int|null $status Status auf den die Kategorie gesetzt werden soll, oder NULL wenn zum nächsten Status weitergeschaltet werden soll
     *
     * @throws ApiFunctionException
     *
     * @return int Der neue Status der Kategorie
     */
    public static function categoryStatus(int $categoryId, int $clang, ?int $status = null): int
    {
        $KAT = Sql::factory();
        $KAT->setQuery('select * from ' . Core::getTablePrefix() . 'article where id=? and clang_id=? and startarticle=1', [$categoryId, $clang]);
        if (1 == $KAT->getRows()) {
            // Status wurde nicht von außen vorgegeben,
            // => zyklisch auf den nächsten Weiterschalten
            if (null === $status) {
                $newstatus = self::nextStatus($KAT->getValue('status'));
            } else {
                $newstatus = $status;
            }

            $EKAT = Sql::factory();
            $EKAT->setTable(Core::getTablePrefix() . 'article');
            $EKAT->setWhere(['id' => $categoryId,  'clang_id' => $clang, 'startarticle' => 1]);
            $EKAT->setValue('status', $newstatus);
            $EKAT->addGlobalUpdateFields(self::getUser());

            $EKAT->update();

            ArticleCache::delete($categoryId, $clang);

            // ----- EXTENSION POINT
            Extension::dispatch(new ExtensionPoint('CAT_STATUS', null, [
                'id' => $categoryId,
                'clang' => $clang,
                'status' => $newstatus,
            ]));
        } else {
            throw new ApiFunctionException(I18n::msg('no_such_category'));
        }

        return $newstatus;
    }

    /**
     * Gibt alle Stati zurück, die für eine Kategorie gültig sind.
     *
     * @return list<array{string, string, string}> Array von Stati
     */
    public static function statusTypes(): array
    {
        /** @var list<array{string, string, string}> $catStatusTypes */
        static $catStatusTypes;

        if (!$catStatusTypes) {
            $catStatusTypes = [
                // Name, CSS-Class, Icon
                [I18n::msg('status_offline'), 'rex-offline', 'rex-icon-offline'],
                [I18n::msg('status_online'), 'rex-online', 'rex-icon-online'],
            ];

            // ----- EXTENSION POINT
            $catStatusTypes = Extension::dispatch(new ExtensionPoint('CAT_STATUS_TYPES', $catStatusTypes));
        }

        return $catStatusTypes;
    }

    public static function nextStatus($currentStatus): int
    {
        $catStatusTypes = self::statusTypes();
        return ($currentStatus + 1) % count($catStatusTypes);
    }

    public static function prevStatus($currentStatus): int
    {
        $catStatusTypes = self::statusTypes();
        if (($currentStatus - 1) < 0) {
            return count($catStatusTypes) - 1;
        }

        return ($currentStatus - 1) % count($catStatusTypes);
    }

    /** Kopiert eine Kategorie in eine andere. */
    public static function copyCategory(int $fromCat, int $toCat): void
    {
        // TODO copyCategory implementieren
    }

    /** Berechnet die Prios der Kategorien in einer Kategorie neu. */
    public static function newCatPrio(int $parentId, int $clang, int $newPrio, int $oldPrio): void
    {
        if ($newPrio != $oldPrio) {
            if ($newPrio < $oldPrio) {
                $addsql = 'desc';
            } else {
                $addsql = 'asc';
            }

            Util::organizePriorities(
                Core::getTable('article'),
                'catpriority',
                'clang_id=' . $clang . ' AND parent_id=' . $parentId . ' AND startarticle=1',
                'catpriority,updatedate ' . $addsql,
            );

            ArticleCache::deleteLists($parentId);
            ArticleCache::deleteMeta($parentId);

            $ids = Sql::factory()->getArray('SELECT id FROM ' . Core::getTable('article') . ' WHERE startarticle=1 AND parent_id = ? GROUP BY id', [$parentId]);
            foreach ($ids as $id) {
                ArticleCache::deleteMeta((int) $id['id']);
            }
        }
    }

    /** Verschieben einer Kategorie in eine andere. */
    public static function moveCategory(int $fromCat, int $toCat): bool
    {
        if ($fromCat == $toCat) {
            // kann nicht in gleiche kategroie kopiert werden
            return false;
        }

        // kategorien vorhanden ?
        // ist die zielkategorie im pfad der quellkategeorie ?
        $fcat = Sql::factory();
        $fcat->setQuery('select * from ' . Core::getTablePrefix() . 'article where startarticle=1 and id=? and clang_id=?', [$fromCat, Language::getStartId()]);

        $tcat = Sql::factory();
        $tcat->setQuery('select * from ' . Core::getTablePrefix() . 'article where startarticle=1 and id=? and clang_id=?', [$toCat, Language::getStartId()]);

        if (1 != $fcat->getRows() || (1 != $tcat->getRows() && 0 != $toCat)) {
            // eine der kategorien existiert nicht
            return false;
        }
        if ($toCat > 0) {
            $tcats = explode('|', (string) $tcat->getValue('path'));
            if (in_array($fromCat, $tcats)) {
                // zielkategorie ist in quellkategorie -> nicht verschiebbar
                return false;
            }
        }

        // ----- folgende cats regenerate
        $RC = [];
        $RC[(int) $fcat->getValue('parent_id')] = 1;
        $RC[$fromCat] = 1;
        $RC[$toCat] = 1;

        if ($toCat > 0) {
            $toPath = $tcat->getValue('path') . $toCat . '|';
        } else {
            $toPath = '|';
        }

        $fromPath = $fcat->getValue('path') . $fromCat . '|';

        $gcats = Sql::factory();
        // $gcats->setDebug();
        $gcats->setQuery('select * from ' . Core::getTablePrefix() . 'article where path like ? and clang_id=?', [$fromPath . '%', Language::getStartId()]);

        $up = Sql::factory();
        // $up->setDebug();
        for ($i = 0; $i < $gcats->getRows(); ++$i) {
            // make update
            $newPath = $toPath . $fromCat . '|' . str_replace($fromPath, '', (string) $gcats->getValue('path'));
            $icid = (int) $gcats->getValue('id');

            // path aendern und speichern
            $up->setTable(Core::getTablePrefix() . 'article');
            $up->setWhere(['id' => $icid]);
            $up->setValue('path', $newPath);
            $up->update();

            // cat in gen eintragen
            $RC[$icid] = 1;

            $gcats->next();
        }

        // ----- clang holen, max catprio holen und entsprechen updaten
        $gmax = Sql::factory();
        $up = Sql::factory();
        // $up->setDebug();
        foreach (Language::getAllIds() as $clang) {
            $gmax->setQuery('select max(catpriority) from ' . Core::getTablePrefix() . 'article where parent_id=? and clang_id=?', [$toCat, $clang]);
            $catpriority = (int) $gmax->getValue('max(catpriority)');
            $up->setTable(Core::getTablePrefix() . 'article');
            $up->setWhere(['id' => $fromCat, 'clang_id' => $clang]);
            $up->setValue('path', $toPath);
            $up->setValue('parent_id', $toCat);
            $up->setValue('catpriority', $catpriority + 1);
            $up->update();
        }

        // ----- generiere artikel neu - ohne neue inhaltsgenerierung
        foreach ($RC as $id => $key) {
            ArticleCache::delete($id);
        }

        foreach (Language::getAllIds() as $clang) {
            self::newCatPrio((int) $fcat->getValue('parent_id'), $clang, 0, 1);

            Extension::dispatch(new ExtensionPoint('CAT_MOVED', null, [
                'id' => $fromCat,
                'clang_id' => $clang, // deprecated, use "clang" instead
                'clang' => $clang,
                'category_id' => $toCat,
            ]));
        }

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
