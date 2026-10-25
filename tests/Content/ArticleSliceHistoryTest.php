<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;

/** @internal */
final class ArticleSliceHistoryTest extends TestCase
{
    public function testMakeSnapshotWithoutUser(): void
    {
        self::assertNull(Core::getUser());

        $articleId = Sql::factory()
            ->setTable('rex_article')
            ->setValues(['parent_id' => null, 'priority' => 1, 'path' => '|'])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();

        try {
            Sql::factory()
                ->setTable('rex_article_translation')
                ->setValues(['article_id' => $articleId, 'language_id' => 1, 'name' => 'test', 'status' => 1])
                ->insert();

            Sql::factory()
                ->setTable('rex_article_slice')
                ->setValues(['article_id' => $articleId, 'language_id' => 1, 'content_section_id' => 1, 'module' => 'test', 'priority' => 1, 'revision' => 0])
                ->addGlobalCreateFields()
                ->addGlobalUpdateFields()
                ->insert();

            ArticleSliceHistory::makeSnapshot($articleId, 1, 'slice_update');

            self::assertSame(
                [['history_type' => 'slice_update', 'history_user' => Core::getEnvironment()->value]],
                array_map(
                    static fn (array $snapshot) => ['history_type' => $snapshot['history_type'], 'history_user' => $snapshot['history_user']],
                    ArticleSliceHistory::getSnapshots($articleId, 1),
                ),
            );
        } finally {
            Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$articleId]);
            Sql::factory()->setTable('rex_article')->setWhere(['id' => $articleId])->delete();
        }
    }

    public function testMakeSnapshotKeepsTheFirstStateWithinTheSameSecond(): void
    {
        $articleId = Sql::factory()
            ->setTable('rex_article')
            ->setValues(['parent_id' => null, 'priority' => 1, 'path' => '|'])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();

        try {
            Sql::factory()
                ->setTable('rex_article_translation')
                ->setValues(['article_id' => $articleId, 'language_id' => 1, 'name' => 'test', 'status' => 1])
                ->insert();

            $addSlice = static fn (int $priority) => Sql::factory()
                ->setTable('rex_article_slice')
                ->setValues(['article_id' => $articleId, 'language_id' => 1, 'content_section_id' => 1, 'module' => 'test', 'priority' => $priority, 'revision' => 0])
                ->addGlobalCreateFields()
                ->addGlobalUpdateFields()
                ->insert()
                ->getLastId();

            $first = $addSlice(1);

            // retry until both snapshots fall into the same second
            do {
                Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$articleId]);
                Sql::factory()->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND id <> ?', [$articleId, $first]);

                $second = time();
                ArticleSliceHistory::makeSnapshot($articleId, 1, 'slice_add');
                $addSlice(2);
                ArticleSliceHistory::makeSnapshot($articleId, 1, 'slice_add');
            } while (time() !== $second);

            $rows = Sql::factory()->getArray('SELECT slice_id FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$articleId]);

            self::assertCount(1, ArticleSliceHistory::getSnapshots($articleId, 1));
            self::assertSame([$first], array_map(static fn (array $row): int => (int) $row['slice_id'], $rows));
        } finally {
            Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$articleId]);
            Sql::factory()->setTable('rex_article')->setWhere(['id' => $articleId])->delete();
        }
    }
}
