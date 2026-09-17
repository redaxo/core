<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Cache;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
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
        $sql->setTable('rex_language');
        $sql->setNewId('id');
        $sql->setValue('code', $code);
        $sql->setValue('name', $name);
        $sql->setValue('priority', $priority);
        $sql->setValue('status', $status);
        $sql->insert();
        $id = $sql->getLastId();

        Util::organizePriorities('rex_language', 'priority', '', 'priority, id != ' . $id);

        $sourceId = Language::getStartId();
        if ($sourceId === $id) {
            foreach (Language::getAllIds(true) as $languageId) {
                if ($sourceId !== $languageId) {
                    $sourceId = $languageId;
                    break;
                }
            }
        }

        // the translations of the source language are the starting point for the new language, articles start offline
        self::copyTranslations('rex_article_translation', $sourceId, $id, ['status' => 0]);
        self::copyTranslations('rex_category_translation', $sourceId, $id);
        self::copyTranslations('rex_media_translation', $sourceId, $id);

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
        $editLang->setTable('rex_language');
        $editLang->setWhere(['id' => $id]);
        $editLang->setValue('code', $code);
        $editLang->setValue('name', $name);
        $editLang->setValue('priority', $priority);
        if (null !== $status) {
            $editLang->setValue('status', $status);
        }
        $editLang->update();

        $comparator = $oldPriority < $priority ? '=' : '!=';
        Util::organizePriorities('rex_language', 'priority', '', 'priority, id' . $comparator . $id);

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
            throw new UserMessageException(I18n::msg('language_error_startidcanotbedeleted', $startLanguageId));
        }

        if (!Language::exists($id)) {
            throw new UserMessageException(I18n::msg('language_error_idcanotbedeleted', $id));
        }

        $language = Language::require($id);

        Sql::factory()->setQuery('delete from rex_language where id=?', [$id]);

        Util::organizePriorities('rex_language', 'priority', '', 'priority');

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
        $lg->setQuery('select * from rex_language order by priority');

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

    /**
     * Copies all rows of a translation table from one language to another.
     *
     * @param non-empty-string $table
     * @param array<string, int|string> $overrides values replacing the copied ones, keyed by column
     */
    private static function copyTranslations(string $table, int $fromLanguageId, int $toLanguageId, array $overrides = []): void
    {
        $sql = Sql::factory();
        $overrides['language_id'] = $toLanguageId;

        $columns = array_keys(Table::get($table)->getColumns());
        $insertColumns = implode(', ', array_map($sql->escapeIdentifier(...), $columns));
        $selectColumns = implode(', ', array_map(
            static fn (string $column): string => isset($overrides[$column]) ? $sql->escape((string) $overrides[$column]) : $sql->escapeIdentifier($column),
            $columns,
        ));

        $sql->setQuery(
            'INSERT INTO ' . $sql->escapeIdentifier($table) . ' (' . $insertColumns . ') SELECT ' . $selectColumns . ' FROM ' . $sql->escapeIdentifier($table) . ' WHERE language_id = ?',
            [$fromLanguageId],
        );
    }
}
