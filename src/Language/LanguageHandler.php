<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\ExtensionPoint\LanguageAdded;
use Redaxo\Core\Language\ExtensionPoint\LanguageDeleted;
use Redaxo\Core\Language\ExtensionPoint\LanguageUpdated;
use Redaxo\Core\Translation\I18n;

final class LanguageHandler
{
    private function __construct() {}

    /** Creates a language and copies the articles of an existing one into it. */
    public static function add(string $code, string $name, int $priority, bool $status = false): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'language');
        $sql->setNewId('id');
        $sql->setValue('code', $code);
        $sql->setValue('name', $name);
        $sql->setValue('priority', $priority);
        $sql->setValue('status', $status);
        $sql->insert();
        $id = $sql->getLastId();

        Util::organizePriorities(Core::getTable('language'), 'priority', '', 'priority, id != ' . $id);

        $sourceId = Language::getStartId();
        if ($sourceId === $id) {
            foreach (Language::getAllIds(true) as $languageId) {
                if ($sourceId !== $languageId) {
                    $sourceId = $languageId;
                    break;
                }
            }
        }

        $firstLang = Sql::factory();
        $firstLang->setQuery('select * from ' . Core::getTablePrefix() . 'article where language_id=?', [$sourceId]);
        $fields = $firstLang->getFieldnames();

        $newLang = Sql::factory();
        // $newLang->setDebug();
        foreach ($firstLang as $firstLangArt) {
            $newLang->setTable(Core::getTablePrefix() . 'article');

            foreach ($fields as $value) {
                if ('pid' == $value) {
                    continue;
                } // nix passiert
                if ('language_id' == $value) {
                    $newLang->setValue('language_id', $id);
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
        Extension::dispatch(new LanguageAdded(Language::require($id)));
    }

    /** Updates a language. */
    public static function edit(int $id, string $code, string $name, int $priority, ?bool $status = null): bool
    {
        if (!Language::exists($id)) {
            throw new RuntimeException('Language with id "' . $id . '" does not exist');
        }

        $oldPriority = Language::require($id)->priority;

        $editLang = Sql::factory();
        $editLang->setTable(Core::getTablePrefix() . 'language');
        $editLang->setWhere(['id' => $id]);
        $editLang->setValue('code', $code);
        $editLang->setValue('name', $name);
        $editLang->setValue('priority', $priority);
        if (null !== $status) {
            $editLang->setValue('status', $status);
        }
        $editLang->update();

        $comparator = $oldPriority < $priority ? '=' : '!=';
        Util::organizePriorities(Core::getTable('language'), 'priority', '', 'priority, id' . $comparator . $id);

        Cache::delete();

        // ----- EXTENSION POINT
        Extension::dispatch(new LanguageUpdated(Language::require($id)));

        return true;
    }

    /**
     * Deletes a language together with its articles and slices.
     *
     * @throws UserMessageException
     */
    public static function delete(int $id): void
    {
        $startLanguageId = Language::getStartId();
        if ($id == $startLanguageId) {
            throw new UserMessageException(I18n::msg('clang_error_startidcanotbedeleted', $startLanguageId));
        }

        if (!Language::exists($id)) {
            throw new UserMessageException(I18n::msg('clang_error_idcanotbedeleted', $id));
        }

        $language = Language::require($id);

        $del = Sql::factory();
        $del->setQuery('delete from ' . Core::getTablePrefix() . 'language where id=?', [$id]);

        Util::organizePriorities(Core::getTable('language'), 'priority', '', 'priority');

        $del->setQuery('delete from ' . Core::getTablePrefix() . 'article where language_id=?', [$id]);
        $del->setQuery('delete from ' . Core::getTablePrefix() . 'article_slice where language_id=?', [$id]);

        Cache::delete();

        // ----- EXTENSION POINT
        Extension::dispatch(new LanguageDeleted($language));
    }

    /**
     * Writes the language properties to the cache file.
     *
     * @return array<int, array<string, scalar|null>>
     */
    public static function generateCache(): array
    {
        $lg = Sql::factory();
        $lg->setQuery('select * from ' . Core::getTablePrefix() . 'language order by priority');

        $languages = [];
        foreach ($lg as $lang) {
            $id = (int) $lang->getValue('id');
            foreach ($lg->getFieldnames() as $field) {
                $languages[$id][$field] = $lang->getValue($field);
            }
        }

        $file = Path::coreCache('language.cache');
        if (!File::putCache($file, $languages)) {
            throw new RuntimeException('Language cache file could not be generated');
        }

        return $languages;
    }
}
