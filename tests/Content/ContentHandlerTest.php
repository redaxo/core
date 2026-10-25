<?php

namespace Redaxo\Core\Tests\Content;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\ArticleSlicesCopying;
use Redaxo\Core\Content\ExtensionPoint\SliceAdding;
use Redaxo\Core\Content\ExtensionPoint\SliceDeleting;
use Redaxo\Core\Content\ExtensionPoint\SliceMoving;
use Redaxo\Core\Content\SliceMoveDirection;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use ReflectionProperty;

/** @internal */
final class ContentHandlerTest extends TestCase
{
    private int $articleId;

    private mixed $extensions;

    /** @var list<ArticleContentUpdated> */
    private array $events = [];

    #[Override]
    protected function setUp(): void
    {
        // the slices need an article to hang off, see the foreign key of `rex_article_slice`
        $this->articleId = self::createArticle();

        $this->extensions = new ReflectionProperty(Extension::class, 'extensions')->getValue();

        Extension::register(ArticleContentUpdated::NAME, function (ArticleContentUpdated $ep): void {
            $this->events[] = $ep;
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        new ReflectionProperty(Extension::class, 'extensions')->setValue(null, $this->extensions);

        // the translation and the slices are removed by the foreign keys
        Sql::factory()
            ->setTable('rex_article')
            ->setWhere(['id' => $this->articleId])
            ->delete();
    }

    public function testSliceActionsDispatchArticleContentUpdatedWithSlice(): void
    {
        ContentHandler::addSlice($this->articleId, 1, 1, 'test', ['text' => 'foo']);
        ContentHandler::addSlice($this->articleId, 1, 1, 'test', ['text' => 'bar']);

        $first = $this->events[0]->slice;
        self::assertNotNull($first);
        self::assertSame(['text' => 'foo'], $first->data);
        self::assertSame(1, $first->priority);

        ContentHandler::moveSlice($first->id, 1, SliceMoveDirection::Down);
        ContentHandler::changeSliceStatus($first->id, 0);
        ContentHandler::deleteSlice($first->id);

        self::assertSame(
            ['slice_added', 'slice_added', 'slice_moved', 'slice_status', 'slice_deleted'],
            array_map(static fn (ArticleContentUpdated $ep) => $ep->action, $this->events),
        );

        [, $second, $moved, $status, $deleted] = array_map(static fn (ArticleContentUpdated $ep) => $ep->slice, $this->events);

        self::assertSame(['text' => 'bar'], $second?->data);
        self::assertNotNull($moved);
        self::assertSame(2, $moved->priority);
        self::assertSame(1, $moved->status);
        self::assertSame(0, $status?->status);
        self::assertSame($first->id, $deleted?->id);
        self::assertNull(ArticleSlice::getArticleSliceById($first->id, 1));
    }

    public function testSliceActionsDispatchEventsBeforehand(): void
    {
        $events = [];
        $listener = static function (SliceAdding|SliceMoving|SliceDeleting|ArticleSlicesCopying $ep) use (&$events): void {
            $events[] = $ep;
        };
        Extension::register([SliceAdding::class, SliceMoving::class, SliceDeleting::class, ArticleSlicesCopying::class], $listener);

        $first = ContentHandler::addSlice($this->articleId, 1, 1, 'test');
        ContentHandler::addSlice($this->articleId, 1, 1, 'test');

        ContentHandler::moveSlice($first->id, 1, SliceMoveDirection::Down);
        ContentHandler::deleteSlice($first->id);

        $targetId = self::createArticle();
        try {
            ContentHandler::copyContent($this->articleId, $targetId);
        } finally {
            Sql::factory()->setTable('rex_article')->setWhere(['id' => $targetId])->delete();
        }

        self::assertSame(
            [SliceAdding::class, SliceAdding::class, SliceMoving::class, SliceDeleting::class, ArticleSlicesCopying::class],
            array_map(static fn (object $ep) => $ep::class, $events),
        );

        $adding = $events[0] ?? null;
        $moving = $events[2] ?? null;
        $deleting = $events[3] ?? null;
        $copying = $events[4] ?? null;

        self::assertInstanceOf(SliceAdding::class, $adding);
        self::assertSame($this->articleId, $adding->article->id);
        self::assertSame('test', $adding->moduleKey);

        // the slice is passed in its state before the move
        self::assertInstanceOf(SliceMoving::class, $moving);
        self::assertSame($first->id, $moving->slice->id);
        self::assertSame(1, $moving->slice->priority);
        self::assertSame(SliceMoveDirection::Down, $moving->direction);

        self::assertInstanceOf(SliceDeleting::class, $deleting);
        self::assertSame($first->id, $deleting->slice->id);

        self::assertInstanceOf(ArticleSlicesCopying::class, $copying);
        self::assertSame($targetId, $copying->article->id);
        self::assertSame($this->articleId, $copying->source->id);
        self::assertNull($copying->revision);
    }

    public function testSlicesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $a = ContentHandler::addSlice($this->articleId, 1, 1, 'test');
        $b = ContentHandler::addSlice($this->articleId, 1, 1, 'test');
        $c = ContentHandler::addSlice($this->articleId, 1, 1, 'test', priority: 2);
        $d = ContentHandler::addSlice($this->articleId, 1, 1, 'test', priority: 1);
        self::assertSame([$d->id, $a->id, $c->id, $b->id], $this->sliceIds());

        ContentHandler::moveSlice($b->id, 1, SliceMoveDirection::Up);
        self::assertSame([$d->id, $a->id, $b->id, $c->id], $this->sliceIds());

        ContentHandler::moveSlice($d->id, 1, SliceMoveDirection::Down);
        self::assertSame([$a->id, $d->id, $b->id, $c->id], $this->sliceIds());
    }

    /** @return list<int> */
    private function sliceIds(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            Sql::factory()->getArray('SELECT id FROM rex_article_slice WHERE article_id = ? ORDER BY priority', [$this->articleId]),
        );
    }

    private static function createArticle(): int
    {
        $id = Sql::factory()
            ->setTable('rex_article')
            ->setValues(['parent_id' => null, 'priority' => 1, 'path' => '|'])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();

        Sql::factory()
            ->setTable('rex_article_translation')
            ->setValues(['article_id' => $id, 'language_id' => 1, 'name' => 'test', 'status' => 1])
            ->insert();

        return $id;
    }
}
