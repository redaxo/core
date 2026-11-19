<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_service_priority_test extends TestCase
{
    /** @var list<array{int, bool}> */
    private array $created = [];

    protected function tearDown(): void
    {
        // On an empty database the first created category gets id 1, which is the default start/notfound article
        $structure = rex_addon::get('structure');
        $startArticleId = rex_article::getSiteStartArticleId();
        $notfoundArticleId = rex_article::getNotfoundArticleId();
        $structure->setProperty('start_article_id', 0);
        $structure->setProperty('notfound_article_id', 0);

        try {
            foreach (array_reverse($this->created) as [$id, $isCategory]) {
                if ($isCategory) {
                    rex_category_service::deleteCategory($id);
                } else {
                    rex_article_service::deleteArticle($id);
                }
            }
        } finally {
            $this->created = [];
            $structure->setProperty('start_article_id', $startArticleId);
            $structure->setProperty('notfound_article_id', $notfoundArticleId);
        }
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
        $name = $this->uniqueName();
        rex_category_service::addCategory($parentId, ['catname' => $name, 'catpriority' => $priority]);

        $id = $this->idByName($name);
        $this->created[] = [$id, true];

        return $id;
    }

    private function addArticle(int $categoryId, int $priority): int
    {
        $name = $this->uniqueName();
        rex_article_service::addArticle([
            'name' => $name,
            'category_id' => $categoryId,
            'priority' => $priority,
            'template_id' => 0,
        ]);

        $id = $this->idByName($name);
        $this->created[] = [$id, false];

        return $id;
    }

    private function uniqueName(): string
    {
        return 'rex_service_priority_test ' . uniqid();
    }

    private function idByName(string $name): int
    {
        $sql = rex_sql::factory()->setQuery('SELECT id FROM ' . rex::getTable('article') . ' WHERE name = ? AND clang_id = 1', [$name]);
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
