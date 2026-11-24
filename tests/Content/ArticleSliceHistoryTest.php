<?php

namespace Redaxo\Core\Tests\Content;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Util\Type;
use ReflectionProperty;

use function time;

/** @internal */
final class ArticleSliceHistoryTest extends TestCase
{
    private int $articleId;

    #[Override]
    protected function setUp(): void
    {
        $this->articleId = Sql::factory()
            ->setTable('rex_article')
            ->setValues(['parent_id' => null, 'priority' => 1, 'path' => '|'])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setValues(['article_id' => $this->articleId, 'language_id' => 1, 'name' => 'test', 'status' => 1])
            ->insert();
    }

    #[Override]
    protected function tearDown(): void
    {
        Sql::factory()->setQuery('DELETE FROM ' . ArticleSliceHistory::getTable() . ' WHERE article_id = ?', [$this->articleId]);
        // the translation and the slices are removed by the foreign keys
        Sql::factory()->setTable('rex_article')->setWhere(['id' => $this->articleId])->delete();
        ArticleCache::delete($this->articleId);
    }

    public function testMakeSnapshotWithoutUser(): void
    {
        self::assertNull(Core::getUser());

        $this->addSlice();

        ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_update');

        self::assertSame(
            [['history_type' => 'slice_update', 'history_user' => Core::getEnvironment()->value]],
            array_map(
                static fn (array $snapshot) => ['history_type' => $snapshot['history_type'], 'history_user' => $snapshot['history_user']],
                ArticleSliceHistory::getSnapshots($this->articleId, 1),
            ),
        );
    }

    public function testGetSlicesQuery(): void
    {
        $first = $this->addSlice(1);
        $this->addSlice(2, status: 0);
        $third = $this->addSlice(3);

        ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_update');
        $historyDate = ArticleSliceHistory::getSnapshots($this->articleId, 1)[0]['history_date'];

        $query = ArticleSliceHistory::getSlicesQuery($historyDate, $this->articleId, 1);

        $slices = [];
        foreach (Sql::factory()->setQuery($query) as $row) {
            $slices[] = ArticleSlice::fromSql($row);
        }

        // original slice ids instead of the ids of the history records, without the offline slice
        self::assertSame([$first, $third], array_map(static fn (ArticleSlice $slice) => $slice->id, $slices));
    }

    public function testMakeSnapshotKeepsTheFirstStateWithinTheSameSecond(): void
    {
        $first = $this->addSlice();
        $table = ArticleSliceHistory::getTable();

        do {
            Sql::factory()->setQuery('DELETE FROM ' . $table . ' WHERE article_id = ?', [$this->articleId]);
            Sql::factory()->setQuery('DELETE FROM rex_article_slice WHERE article_id = ? AND id <> ?', [$this->articleId, $first]);

            $second = time();
            ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_add');
            $this->addSlice(2);
            ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_add');
        } while (time() !== $second);

        $rows = Sql::factory()->getArray('SELECT slice_id FROM ' . $table . ' WHERE article_id = ?', [$this->articleId]);

        self::assertCount(1, ArticleSliceHistory::getSnapshots($this->articleId, 1));
        self::assertSame([$first], array_map(static fn (array $row): int => (int) $row['slice_id'], $rows));
    }

    public function testRestoreSnapshotDispatchesArticleContentUpdated(): void
    {
        $this->addSlice();

        ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_update');
        $historyDate = ArticleSliceHistory::getSnapshots($this->articleId, 1)[0]['history_date'];

        $extensions = new ReflectionProperty(Extension::class, 'extensions');
        $registered = Type::array($extensions->getValue());

        $events = [];
        Extension::register(ArticleContentUpdated::NAME, static function (ArticleContentUpdated $ep) use (&$events): void {
            $events[] = $ep;
        });

        try {
            self::assertTrue(ArticleSliceHistory::restoreSnapshot($historyDate, $this->articleId, 1));
        } finally {
            $extensions->setValue(null, $registered);
        }

        self::assertCount(1, $events);
        self::assertSame('snapshot_restored', $events[0]->action);
        self::assertSame($this->articleId, $events[0]->article->id);
    }

    public function testRestoreSnapshotOfMissingArticle(): void
    {
        $this->addSlice();

        ArticleSliceHistory::makeSnapshot($this->articleId, 1, 'slice_update');
        $historyDate = ArticleSliceHistory::getSnapshots($this->articleId, 1)[0]['history_date'];

        Sql::factory()->setTable('rex_article')->setWhere(['id' => $this->articleId])->delete();
        ArticleCache::delete($this->articleId);

        self::assertFalse(ArticleSliceHistory::restoreSnapshot($historyDate, $this->articleId, 1));
    }

    private function addSlice(int $priority = 1, int $status = 1): int
    {
        return Sql::factory()
            ->setTable('rex_article_slice')
            ->setValues([
                'article_id' => $this->articleId,
                'language_id' => 1,
                'content_section_id' => 1,
                'module' => 'test',
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
