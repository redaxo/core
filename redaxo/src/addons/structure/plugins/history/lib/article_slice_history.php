<?php

/**
 * @author dergel
 *
 * @package redaxo\structure\history
 */
class rex_article_slice_history
{
    /**
     * @return non-empty-string
     *
     * @phpstandba-inference-placeholder 'rex_article_slice_history'
     */
    public static function getTable()
    {
        return rex::getTablePrefix() . 'article_slice_history';
    }

    /**
     * Only Snapshots from LiveVersion.
     *
     * @param int $articleId
     * @param int $clangId
     * @param string $historyType
     * @return void
     */
    public static function makeSnapshot($articleId, $clangId, $historyType)
    {
        self::checkTables();

        $slices = rex_sql::factory()->getArray(
            'select * from ' . rex::getTable('article_slice') . ' where article_id=? and clang_id=? and revision=?',
            [
                $articleId,
                $clangId,
                0,
            ],
        );

        $historyDate = date(rex_sql::FORMAT_DATETIME);

        foreach ($slices as $slice) {
            $sql = rex_sql::factory();
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
            $sql->setValue('history_user', rex::requireUser()->getValue('login'));
            $sql->insert();
        }
    }

    /**
     * @param int $articleId
     * @param int $clangId
     *
     * @return array
     */
    public static function getSnapshots($articleId, $clangId)
    {
        $sql = rex_sql::factory();
        return $sql->getArray(
            'select distinct history_date, history_type, history_user from ' . $sql->escapeIdentifier(self::getTable()) . ' where article_id=? and clang_id=? and revision=? order by history_date desc',
            [$articleId, $clangId, 0],
        );
    }

    /**
     * @param string $historyDate
     * @param int $articleId
     * @param int $clangId
     *
     * @return bool
     */
    public static function restoreSnapshot($historyDate, $articleId, $clangId)
    {
        self::checkTables();

        $sql = rex_sql::factory();
        $slices = $sql->getArray('select id from ' . $sql->escapeIdentifier(self::getTable()) . ' where article_id=? and clang_id=? and revision=? and history_date=?', [$articleId, $clangId, 0, $historyDate]);

        if (0 == count($slices)) {
            return false;
        }

        self::makeSnapshot($articleId, $clangId, 'version set ' . $historyDate);

        $articleSlicesTable = rex_sql_table::get(rex::getTable('article_slice'));

        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(rex::getTable('article_slice')) . ' where article_id=? and clang_id=? and revision=?', [$articleId, $clangId, 0]);

        $slices = rex_sql::factory();
        $slices = $slices->getArray('select * from ' . $slices->escapeIdentifier(self::getTable()) . ' where article_id=? and clang_id=? and revision=? and history_date=?', [$articleId, $clangId, 0, $historyDate]);

        foreach ($slices as $slice) {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('article_slice'));

            $ignoreFields = ['id', 'slice_id', 'history_date', 'history_type', 'history_user'];
            foreach ($articleSlicesTable->getColumns() as $column) {
                $columnName = $column->getName();
                if (!in_array($columnName, $ignoreFields)) {
                    $sql->setValue($columnName, $slice[$columnName]);
                }
            }

            $sql->insert();
        }
        rex_article_cache::delete($articleId, $clangId);
        return true;
    }

    /**
     * Query for the slices of a snapshot, in the shape of the regular slices query of the article content
     * (`ART_SLICES_QUERY`) in the frontend.
     *
     * The slices keep their original ids, so that the id refers to the slice and not to the history record.
     * Like in the frontend, offline slices are left out.
     *
     * @internal
     */
    public static function getSlicesQuery(string $historyDate, int $articleId, int $clangId): string
    {
        self::checkTables();

        $sql = rex_sql::factory();
        $columns = [];
        foreach (rex_sql_table::get(rex::getTable('article_slice'))->getColumns() as $column) {
            if ('id' !== $column->getName()) {
                $columns[] = $sql->escapeIdentifier($column->getName());
            }
        }

        $prefix = rex::getTablePrefix();
        $historyTable = $sql->escapeIdentifier(self::getTable());
        $columns = implode(', ', $columns);
        $historyDate = $sql->escape($historyDate);
        $articleLimit = 0 !== $articleId ? 'AND article_id = ' . $articleId : '';

        return <<<SQL
            SELECT
                {$prefix}module.id, {$prefix}module.key, {$prefix}module.name, {$prefix}module.output, {$prefix}module.input,
                {$prefix}article_slice.*,
                {$prefix}article.parent_id
            FROM (
                SELECT slice_id AS id, {$columns}
                FROM {$historyTable}
                WHERE history_date = {$historyDate} AND clang_id = {$clangId} AND revision = 0 AND status = 1
                    {$articleLimit}
            ) AS {$prefix}article_slice
            LEFT JOIN {$prefix}module ON {$prefix}article_slice.module_id = {$prefix}module.id
            LEFT JOIN {$prefix}article ON {$prefix}article_slice.article_id = {$prefix}article.id
            WHERE {$prefix}article.clang_id = {$clangId}
            ORDER BY {$prefix}article_slice.priority
            SQL;
    }

    /** @return void */
    public static function clearAllHistory()
    {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()));
    }

    public static function clearHistoryByDate(DateTimeInterface $deleteDate): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()) . ' where history_date < ?', [$deleteDate->format(rex_sql::FORMAT_DATETIME)]);
    }

    /** @return void */
    public static function checkTables()
    {
        $slicesTable = rex_sql_table::get(rex::getTable('article_slice'));
        $historyTable = rex_sql_table::get(self::getTable());

        foreach ($slicesTable->getColumns() as $column) {
            if ('id' != strtolower($column->getName())) {
                $historyTable->ensureColumn($column);
            }
        }

        $historyTable->alter();
    }
}
