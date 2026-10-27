<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_article_slice_history_test extends TestCase
{
    private static bool $dropTable = false;

    private int $articleId;
    private ?rex_user $user;

    public static function setUpBeforeClass(): void
    {
        // the history plugin is not installed by default, its table is created for these tests only
        if (!rex_sql_table::get(rex_article_slice_history::getTable())->exists()) {
            rex_plugin::require('structure', 'history')->includeFile(rex_package::FILE_INSTALL);
            self::$dropTable = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$dropTable) {
            rex_sql_table::get(rex_article_slice_history::getTable())->drop();
        }
    }

    protected function setUp(): void
    {
        $this->user = rex::getUser();
        rex::setProperty('user', new rex_user(rex_sql::factory()->setQuery("SELECT 1 AS id, 'test' AS login")));

        rex_article_service::addArticle(['name' => 'test', 'category_id' => 0, 'priority' => 1, 'template_id' => 0]);
        $sql = rex_sql::factory()->setQuery('SELECT MAX(id) AS id FROM ' . rex::getTable('article'));
        $this->articleId = (int) $sql->getValue('id');
    }

    protected function tearDown(): void
    {
        rex::setProperty('user', $this->user);

        foreach ([rex_article_slice_history::getTable(), rex::getTable('article_slice')] as $table) {
            rex_sql::factory()->setTable($table)->setWhere(['article_id' => $this->articleId])->delete();
        }
        rex_sql::factory()->setTable(rex::getTable('article'))->setWhere(['id' => $this->articleId])->delete();
        rex_article_cache::delete($this->articleId);
    }

    public function testGetSlicesQuery(): void
    {
        $first = $this->addSlice(1);
        $this->addSlice(2, status: 0);
        $third = $this->addSlice(3);

        rex_article_slice_history::makeSnapshot($this->articleId, 1, 'slice_update');
        $snapshot = rex_type::array(rex_article_slice_history::getSnapshots($this->articleId, 1)[0]);
        $historyDate = rex_type::string($snapshot['history_date']);

        $query = rex_article_slice_history::getSlicesQuery($historyDate, $this->articleId, 1);

        $slices = [];
        foreach (rex_sql::factory()->setQuery($query) as $row) {
            $slices[] = rex_article_slice::fromSql($row);
        }

        // original slice ids instead of the ids of the history records, without the offline slice
        self::assertSame([$first, $third], array_map(static fn (rex_article_slice $slice) => $slice->getId(), $slices));
    }

    private function addSlice(int $priority = 1, int $ctypeId = 1, int $status = 1): int
    {
        return (int) rex_sql::factory()
            ->setTable(rex::getTable('article_slice'))
            ->setValues([
                'article_id' => $this->articleId,
                'clang_id' => 1,
                'ctype_id' => $ctypeId,
                'module_id' => 1,
                'priority' => $priority,
                'status' => $status,
                'revision' => 0,
            ])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();
    }
}
