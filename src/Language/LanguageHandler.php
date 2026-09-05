<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Translation\I18n;

final class LanguageHandler
{
    private function __construct() {}

    /** Erstellt eine Clang. */
    public static function addCLang(string $code, string $name, int $priority, bool $status = false): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'clang');
        $sql->setNewId('id');
        $sql->setValue('code', $code);
        $sql->setValue('name', $name);
        $sql->setValue('priority', $priority);
        $sql->setValue('status', $status);
        $sql->insert();
        $id = $sql->getLastId();

        Util::organizePriorities(Core::getTable('clang'), 'priority', '', 'priority, id != ' . $id);

        $sourceId = Language::getStartId();
        if ($sourceId === $id) {
            foreach (Language::getAllIds(true) as $clangId) {
                if ($sourceId !== $clangId) {
                    $sourceId = $clangId;
                    break;
                }
            }
        }

        $firstLang = Sql::factory();
        $firstLang->setQuery('select * from ' . Core::getTablePrefix() . 'article where clang_id=?', [$sourceId]);
        $fields = $firstLang->getFieldnames();

        $newLang = Sql::factory();
        // $newLang->setDebug();
        foreach ($firstLang as $firstLangArt) {
            $newLang->setTable(Core::getTablePrefix() . 'article');

            foreach ($fields as $value) {
                if ('pid' == $value) {
                    continue;
                } // nix passiert
                if ('clang_id' == $value) {
                    $newLang->setValue('clang_id', $id);
                } elseif ('status' == $value) {
                    $newLang->setValue('status', '0');
                } // Alle neuen Artikel offline
                else {
                    $newLang->setValue($value, $firstLangArt->getValue($value));
                }
            }

            $newLang->insert();
        }

        Cache::delete();

        // ----- EXTENSION POINT
        $clang = Language::require($id);
        Extension::dispatch(new ExtensionPoint('CLANG_ADDED', '', [
            'id' => $clang->id,
            'name' => $clang->name,
            'clang' => $clang,
        ]));
    }

    /** Ändert eine Clang. */
    public static function editCLang(int $id, string $code, string $name, int $priority, ?bool $status = null): bool
    {
        if (!Language::exists($id)) {
            throw new RuntimeException('Language with id "' . $id . '" does not exist');
        }

        $oldPriority = Language::require($id)->priority;

        $editLang = Sql::factory();
        $editLang->setTable(Core::getTablePrefix() . 'clang');
        $editLang->setWhere(['id' => $id]);
        $editLang->setValue('code', $code);
        $editLang->setValue('name', $name);
        $editLang->setValue('priority', $priority);
        if (null !== $status) {
            $editLang->setValue('status', $status);
        }
        $editLang->update();

        $comparator = $oldPriority < $priority ? '=' : '!=';
        Util::organizePriorities(Core::getTable('clang'), 'priority', '', 'priority, id' . $comparator . $id);

        Cache::delete();

        // ----- EXTENSION POINT
        $clang = Language::require($id);
        Extension::dispatch(new ExtensionPoint('CLANG_UPDATED', '', [
            'id' => $clang->id,
            'name' => $clang->name,
            'clang' => $clang,
        ]));

        return true;
    }

    /**
     * Löscht eine Clang.
     *
     * @throws UserMessageException
     */
    public static function deleteCLang(int $id): void
    {
        $startClang = Language::getStartId();
        if ($id == $startClang) {
            throw new UserMessageException(I18n::msg('clang_error_startidcanotbedeleted', $startClang));
        }

        if (!Language::exists($id)) {
            throw new UserMessageException(I18n::msg('clang_error_idcanotbedeleted', $id));
        }

        $clang = Language::require($id);

        $del = Sql::factory();
        $del->setQuery('delete from ' . Core::getTablePrefix() . 'clang where id=?', [$id]);

        Util::organizePriorities(Core::getTable('clang'), 'priority', '', 'priority');

        $del->setQuery('delete from ' . Core::getTablePrefix() . 'article where clang_id=?', [$id]);
        $del->setQuery('delete from ' . Core::getTablePrefix() . 'article_slice where clang_id=?', [$id]);

        Cache::delete();

        // ----- EXTENSION POINT
        Extension::dispatch(new ExtensionPoint('CLANG_DELETED', '', [
            'id' => $clang->id,
            'name' => $clang->name,
            'clang' => $clang,
        ]));
    }

    /**
     * Schreibt Spracheigenschaften in die Datei include/clang.php.
     *
     * @return array<int, array<string, scalar|null>>
     */
    public static function generateCache(): array
    {
        $lg = Sql::factory();
        $lg->setQuery('select * from ' . Core::getTablePrefix() . 'clang order by priority');

        $clangs = [];
        foreach ($lg as $lang) {
            $id = (int) $lang->getValue('id');
            foreach ($lg->getFieldnames() as $field) {
                $clangs[$id][$field] = $lang->getValue($field);
            }
        }

        $file = Path::coreCache('clang.cache');
        if (!File::putCache($file, $clangs)) {
            throw new RuntimeException('Language cache file could not be generated');
        }

        return $clangs;
    }
}
