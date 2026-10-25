<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleSlice;
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

    public function testGetSlicesQuery(): void
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

            $sliceIds = [];
            foreach ([[2, 1], [1, 2], [1, 1]] as [$contentSectionId, $priority]) {
                $sliceIds[] = Sql::factory()
                    ->setTable('rex_article_slice')
                    ->setValues(['article_id' => $articleId, 'language_id' => 1, 'content_section_id' => $contentSectionId, 'module' => 'test', 'priority' => $priority, 'revision' => 0])
                    ->addGlobalCreateFields()
                    ->addGlobalUpdateFields()
                    ->insert()
                    ->getLastId();
            }

            ArticleSliceHistory::makeSnapshot($articleId, 1, 'slice_update');
            $historyDate = ArticleSliceHistory::getSnapshots($articleId, 1)[0]['history_date'];

            $sql = Sql::factory()->setQuery(ArticleSliceHistory::getSlicesQuery($historyDate, $articleId, 1));

            $slices = [];
            foreach ($sql as $row) {
                $slices[] = ArticleSlice::fromSql($row);
            }

            // original slice ids, ordered by content section and priority
            self::assertSame([$sliceIds[2], $sliceIds[1], $sliceIds[0]], array_map(static fn (ArticleSlice $slice) => $slice->id, $slices));
            self::assertSame([1, 1, 2], array_map(static fn (ArticleSlice $slice) => $slice->contentSectionId, $slices));
        } finally {
            Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$articleId]);
            Sql::factory()->setTable('rex_article')->setWhere(['id' => $articleId])->delete();
        }
    }
}
