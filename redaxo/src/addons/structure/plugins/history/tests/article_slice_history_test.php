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

    public function testMakeSnapshotKeepsTheFirstStateWithinTheSameSecond(): void
    {
        $first = $this->addSlice();
        $table = rex_article_slice_history::getTable();

        do {
            rex_sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ?', [$this->articleId]);
            rex_sql::factory()->setQuery(
                'DELETE FROM ' . rex::getTable('article_slice') . ' WHERE article_id = ? AND id <> ?',
                [$this->articleId, $first],
            );

            $second = time();
            rex_article_slice_history::makeSnapshot($this->articleId, 1, 'slice_add');
            $this->addSlice(2);
            rex_article_slice_history::makeSnapshot($this->articleId, 1, 'slice_add');
        } while (time() !== $second);

        $rows = rex_sql::factory()->getArray(
            'SELECT slice_id FROM ' . $table . ' WHERE article_id = ?',
            [$this->articleId],
        );

        self::assertCount(1, rex_article_slice_history::getSnapshots($this->articleId, 1));
        self::assertSame([$first], array_map(static fn (array $row): int => (int) $row['slice_id'], $rows));
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
