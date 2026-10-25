<?php

namespace Redaxo\Core\Content;

use DateTimeInterface;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

use function count;
use function in_array;

final class ArticleSliceHistory
{
    private function __construct() {}

    /**
     * @return non-empty-string
     * @phpstandba-inference-placeholder 'rex_article_slice_history'
     */
    public static function getTable(): string
    {
        return 'rex_article_slice_history';
    }

    /** Only Snapshots from LiveVersion. */
    public static function makeSnapshot(int $articleId, int $languageId, string $historyType): void
    {
        self::checkTables();

        $slices = Sql::factory()->getArray(
            'select * from rex_article_slice where article_id=? and language_id=? and revision=?',
            [
                $articleId,
                $languageId,
                0,
            ],
        );

        $historyDate = date(Sql::FORMAT_DATETIME);

        foreach ($slices as $slice) {
            $sql = Sql::factory();
            $sql->setTable(self::getTable());
            foreach ($slice as $k => $v) {
                if ('id' == $k) {
                    $sql->setValue('slice_id', $v);
                } else {
                    $sql->setValue($k, $v);
                }
            }
            $sql->setValue('history_type', $historyType);
            $sql->setValue('history_date', $historyDate);
            $sql->setValue('history_user', Core::getUser()->login ?? Core::getEnvironment()->value);
            $sql->insert();
        }
    }

    /** @return list<array{history_date: string, history_type: string, history_user: string}> */
    public static function getSnapshots(int $articleId, int $languageId): array
    {
        $sql = Sql::factory();

        /** @var list<array{history_date: string, history_type: string, history_user: string}> */
        return $sql->getArray(
            'select distinct history_date, history_type, history_user from ' . $sql->escapeIdentifier(self::getTable()) . ' where article_id=? and language_id=? and revision=? order by history_date desc',
            [$articleId, $languageId, 0],
        );
    }

    public static function restoreSnapshot(string $historyDate, int $articleId, int $languageId): bool
    {
        self::checkTables();

        $sql = Sql::factory();
        $slices = $sql->getArray('select id from ' . $sql->escapeIdentifier(self::getTable()) . ' where article_id=? and language_id=? and revision=? and history_date=?', [$articleId, $languageId, 0, $historyDate]);

        if (0 == count($slices)) {
            return false;
        }

        self::makeSnapshot($articleId, $languageId, 'version set ' . $historyDate);

        $articleSlicesTable = Table::get('rex_article_slice');

        $sql = Sql::factory();
        $sql->setQuery('delete from rex_article_slice where article_id=? and language_id=? and revision=?', [$articleId, $languageId, 0]);

        $slices = Sql::factory();
        $slices = $slices->getArray('select * from ' . $slices->escapeIdentifier(self::getTable()) . ' where article_id=? and language_id=? and revision=? and history_date=?', [$articleId, $languageId, 0, $historyDate]);

        foreach ($slices as $slice) {
            $sql = Sql::factory();
            $sql->setTable('rex_article_slice');

            $ignoreFields = ['id', 'slice_id', 'history_date', 'history_type', 'history_user'];
            foreach ($articleSlicesTable->getColumns() as $column) {
                $columnName = $column->name;
                if (!in_array($columnName, $ignoreFields)) {
                    $sql->setValue($columnName, $slice[$columnName]);
                }
            }

            $sql->insert();

            if ($slice = ArticleSlice::getArticleSliceById($sql->getLastId(), $languageId)) {
                ArticleSliceReference::update($slice);
            }
        }
        ArticleCache::delete($articleId, $languageId);
        return true;
    }

    /**
     * Query for the slices of a snapshot, in the shape of the regular slices query of the article content (`ARTICLE_SLICES_QUERY`).
     *
     * The slices keep their original ids, so that `ArticleSlice::$id` refers to the slice and not to the history record.
     *
     * @internal
     */
    public static function getSlicesQuery(string $historyDate, int $articleId, int $languageId): string
    {
        self::checkTables();

        $sql = Sql::factory();
        $columns = [];
        foreach (Table::get('rex_article_slice')->getColumns() as $column) {
            if ('id' !== $column->name) {
                $columns[] = $sql->escapeIdentifier($column->name);
            }
        }

        $articleLimit = 0 !== $articleId ? ' AND article_id = ' . $articleId : '';

        return 'SELECT rex_article_slice.*, rex_article.parent_id
            FROM (
                SELECT slice_id AS id, ' . implode(', ', $columns) . '
                FROM ' . $sql->escapeIdentifier(self::getTable()) . '
                WHERE history_date = ' . $sql->escape($historyDate) . ' AND language_id = ' . $languageId . ' AND revision = 0' . $articleLimit . '
            ) AS rex_article_slice
            LEFT JOIN rex_article ON rex_article_slice.article_id = rex_article.id
            ORDER BY rex_article_slice.content_section_id, rex_article_slice.priority';
    }

    public static function clearAllHistory(): void
    {
        $sql = Sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()));
    }

    public static function clearHistoryByDate(DateTimeInterface $deleteDate): void
    {
        $sql = Sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()) . ' where history_date < ?', [$deleteDate->format(Sql::FORMAT_DATETIME)]);
    }

    public static function checkTables(): void
    {
        $slicesTable = Table::get('rex_article_slice');
        $historyTable = Table::get(self::getTable());

        foreach ($slicesTable->getColumns() as $column) {
            if ('id' != strtolower($column->name)) {
                $historyTable->ensureColumn($column);
            }
        }

        $historyTable->alter();
    }
}
