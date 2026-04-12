<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;

/** @internal */
final class ArticleSliceTest extends TestCase
{
    private const FAKE_ID = 2_147_483_647; // max int on 32bit

    protected function tearDown(): void
    {
        Sql::factory()
            ->setTable(Core::getTable('article_slice'))
            ->setWhere(['article_id' => self::FAKE_ID])
            ->delete();
    }

    public function testGetValue(): void
    {
        $id = $this->addSlice(1, 1);

        $slice = ArticleSlice::getArticleSliceById($id);

        self::assertNotNull($slice);
        self::assertSame('foo', $slice->getValue(1));
        self::assertNull($slice->getValue(2));
    }

    public function testGetNextSlice(): void
    {
        $id = $this->addSlice(1, 1);
        $next = $this->addSlice(2, 0);
        $this->addSlice(4, 1);
        $nextOnline = $this->addSlice(3, 1);
        $this->addSlice(5, 1);

        $slice = ArticleSlice::getArticleSliceById($id);

        self::assertNotNull($slice);
        self::assertSame($next, $slice->getNextSlice()?->id);
        self::assertSame($nextOnline, $slice->getNextSlice(true)?->id);
    }

    public function testGetPreviousSlice(): void
    {
        $this->addSlice(1, 1);
        $previousOnline = $this->addSlice(3, 1);
        $this->addSlice(2, 1);
        $previous = $this->addSlice(4, 0);
        $id = $this->addSlice(5, 1);

        $slice = ArticleSlice::getArticleSliceById($id);

        self::assertNotNull($slice);
        self::assertSame($previous, $slice->getPreviousSlice()?->id);
        self::assertSame($previousOnline, $slice->getPreviousSlice(true)?->id);
    }

    private function addSlice(int $priority, int $status): int
    {
        $sql = Sql::factory();
        $sql
            ->setTable(Core::getTable('article_slice'))
            ->setValues([
                'article_id' => self::FAKE_ID,
                'clang_id' => 1,
                'ctype_id' => 1,
                'module' => 'test',
                'revision' => 0,
                'priority' => $priority,
                'status' => $status,
                'value1' => 'foo',
            ])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert();

        return $sql->getLastId();
    }
}
