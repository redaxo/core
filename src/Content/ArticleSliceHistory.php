<?php

namespace Redaxo\Core\Content;

use DateTimeInterface;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

use function count;
use function in_array;

class ArticleSliceHistory
{
    /**
     * @return non-empty-string
     *
     * @phpstandba-inference-placeholder 'rex_article_slice_history'
     */
    public static function getTable()
    {
        return Core::getTablePrefix() . 'article_slice_history';
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

        $slices = Sql::factory()->getArray(
            'select * from ' . Core::getTable('article_slice') . ' where article_id=? and clang_id=? and revision=?',
            [
                $articleId,
                $clangId,
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
            $sql->setValue('history_user', Core::requireUser()->getValue('login'));
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
        $sql = Sql::factory();
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

        $sql = Sql::factory();
        $slices = $sql->getArray('select id from ' . $sql->escapeIdentifier(self::getTable()) . ' where article_id=? and clang_id=? and revision=? and history_date=?', [$articleId, $clangId, 0, $historyDate]);

        if (0 == count($slices)) {
            return false;
        }

        self::makeSnapshot($articleId, $clangId, 'version set ' . $historyDate);

        $articleSlicesTable = Table::get(Core::getTable('article_slice'));

        $sql = Sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(Core::getTable('article_slice')) . ' where article_id=? and clang_id=? and revision=?', [$articleId, $clangId, 0]);

        $slices = Sql::factory();
        $slices = $slices->getArray('select * from ' . $slices->escapeIdentifier(self::getTable()) . ' where article_id=? and clang_id=? and revision=? and history_date=?', [$articleId, $clangId, 0, $historyDate]);

        foreach ($slices as $slice) {
            $sql = Sql::factory();
            $sql->setTable(Core::getTable('article_slice'));

            $ignoreFields = ['id', 'slice_id', 'history_date', 'history_type', 'history_user'];
            foreach ($articleSlicesTable->getColumns() as $column) {
                $columnName = $column->getName();
                if (!in_array($columnName, $ignoreFields)) {
                    $sql->setValue($columnName, $slice[$columnName]);
                }
            }

            $sql->insert();
        }
        ArticleCache::delete($articleId, $clangId);
        return true;
    }

    /** @return void */
    public static function clearAllHistory()
    {
        $sql = Sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()));
    }

    public static function clearHistoryByDate(DateTimeInterface $deleteDate): void
    {
        $sql = Sql::factory();
        $sql->setQuery('delete from ' . $sql->escapeIdentifier(self::getTable()) . ' where history_date < ?', [$deleteDate->format(Sql::FORMAT_DATETIME)]);
    }

    /** @return void */
    public static function checkTables()
    {
        $slicesTable = Table::get(Core::getTable('article_slice'));
        $historyTable = Table::get(self::getTable());

        foreach ($slicesTable->getColumns() as $column) {
            if ('id' != strtolower($column->getName())) {
                $historyTable->ensureColumn($column);
            }
        }

        $historyTable->alter();
    }
}
