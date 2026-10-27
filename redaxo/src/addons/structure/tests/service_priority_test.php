<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_service_priority_test extends TestCase
{
    /** @var list<int> */
    private array $ids = [];

    protected function tearDown(): void
    {
        foreach ($this->ids as $id) {
            rex_sql::factory()->setTable(rex::getTable('article'))->setWhere(['id' => $id])->delete();
            rex_article_cache::delete($id);
        }
        $this->ids = [];
    }

    public function testArticlesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $category = $this->addCategory(0, 1);
        $a = $this->addArticle($category, 2);
        $b = $this->addArticle($category, 3);
        $c = $this->addArticle($category, 4);
        $d = $this->addArticle($category, 2);
        self::assertSame([$category, $d, $a, $b, $c], $this->articleIds($category));

        rex_article_service::editArticle($c, 1, ['name' => 'test', 'template_id' => 0, 'priority' => 2]);
        self::assertSame([$category, $c, $d, $a, $b], $this->articleIds($category));

        rex_article_service::editArticle($c, 1, ['name' => 'test', 'template_id' => 0, 'priority' => 3]);
        self::assertSame([$category, $d, $c, $a, $b], $this->articleIds($category));
    }

    public function testCategoriesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $parent = $this->addCategory(0, 1);
        $a = $this->addCategory($parent, 1);
        $b = $this->addCategory($parent, 2);
        $c = $this->addCategory($parent, 3);
        $d = $this->addCategory($parent, 1);
        self::assertSame([$d, $a, $b, $c], $this->categoryIds($parent));

        rex_category_service::editCategory($c, 1, ['catpriority' => 1]);
        self::assertSame([$c, $d, $a, $b], $this->categoryIds($parent));

        rex_category_service::editCategory($c, 1, ['catpriority' => 2]);
        self::assertSame([$d, $c, $a, $b], $this->categoryIds($parent));
    }

    private function addCategory(int $parentId, int $priority): int
    {
        rex_category_service::addCategory($parentId, ['catname' => 'test', 'catpriority' => $priority]);

        return $this->ids[] = $this->lastId();
    }

    private function addArticle(int $categoryId, int $priority): int
    {
        rex_article_service::addArticle([
            'name' => 'test',
            'category_id' => $categoryId,
            'priority' => $priority,
            'template_id' => 0,
        ]);

        return $this->ids[] = $this->lastId();
    }

    private function lastId(): int
    {
        $sql = rex_sql::factory()->setQuery('SELECT MAX(id) AS id FROM ' . rex::getTable('article'));
        return (int) $sql->getValue('id');
    }

    /** @return list<int> */
    private function articleIds(int $categoryId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('article') . '
                    WHERE clang_id = 1 AND ((startarticle = 0 AND parent_id = :id) OR (startarticle = 1 AND id = :id))
                    ORDER BY priority',
                ['id' => $categoryId],
            ),
        );
    }

    /** @return list<int> */
    private function categoryIds(int $parentId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('article') . '
                    WHERE clang_id = 1 AND startarticle = 1 AND parent_id = ?
                    ORDER BY catpriority',
                [$parentId],
            ),
        );
    }
}
