<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_content_service_test extends TestCase
{
    private int $articleId;
    private int $moduleId;

    protected function setUp(): void
    {
        rex_article_service::addArticle(['name' => 'test', 'category_id' => 0, 'priority' => 1, 'template_id' => 0]);
        $sql = rex_sql::factory()->setQuery('SELECT MAX(id) AS id FROM ' . rex::getTable('article'));
        $this->articleId = (int) $sql->getValue('id');

        $this->moduleId = (int) rex_sql::factory()
            ->setTable(rex::getTable('module'))
            ->setValues(['name' => 'test', 'input' => '', 'output' => ''])
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields()
            ->insert()
            ->getLastId();
    }

    protected function tearDown(): void
    {
        rex_sql::factory()
            ->setTable(rex::getTable('article_slice'))
            ->setWhere(['article_id' => $this->articleId])
            ->delete();
        rex_sql::factory()->setTable(rex::getTable('article'))->setWhere(['id' => $this->articleId])->delete();
        rex_article_cache::delete($this->articleId);
        rex_sql::factory()->setTable(rex::getTable('module'))->setWhere(['id' => $this->moduleId])->delete();
    }

    public function testSlicesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $a = $this->addSlice();
        $b = $this->addSlice();
        $c = $this->addSlice(2);
        $d = $this->addSlice(1);
        self::assertSame([$d, $a, $c, $b], $this->sliceIds());

        rex_content_service::moveSlice($b, 1, 'moveup');
        self::assertSame([$d, $a, $b, $c], $this->sliceIds());

        rex_content_service::moveSlice($d, 1, 'movedown');
        self::assertSame([$a, $d, $b, $c], $this->sliceIds());
    }

    private function addSlice(?int $priority = null): int
    {
        $data = null === $priority ? [] : ['priority' => $priority];
        rex_content_service::addSlice($this->articleId, 1, 1, $this->moduleId, $data);

        $sql = rex_sql::factory()->setQuery('SELECT MAX(id) AS id FROM ' . rex::getTable('article_slice'));
        return (int) $sql->getValue('id');
    }

    /** @return list<int> */
    private function sliceIds(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE article_id = ? ORDER BY priority',
                [$this->articleId],
            ),
        );
    }
}
