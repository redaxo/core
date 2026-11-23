<?php

namespace Redaxo\Core\Tests\Content;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;

/** @internal */
final class CategoryHandlerTest extends TestCase
{
    /** @var list<int> */
    private array $categoryIds = [];

    /** @var list<int> */
    private array $articleIds = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->articleIds as $id) {
            ArticleHandler::delete($id);
        }
        foreach (array_reverse($this->categoryIds) as $id) {
            CategoryHandler::delete($id);
        }
    }

    public function testAdd(): void
    {
        $first = CategoryHandler::add(['parent_id' => null, 'name' => 'first']);
        $this->categoryIds[] = $first->id;
        $second = CategoryHandler::add(['parent_id' => null, 'name' => 'second']);
        $this->categoryIds[] = $second->id;

        self::assertSame($first->priority + 1, $second->priority);
        self::assertSame('second', $second->name);
        self::assertSame('second', $second->getStartArticle()->name);

        $child = CategoryHandler::add(['parent_id' => $second->id, 'name' => 'child', 'priority' => 1]);
        $this->categoryIds[] = $child->id;

        self::assertSame($second->id, $child->parentId);
        self::assertSame(1, $child->priority);
    }

    public function testAddRequiresParentId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @psalm-suppress InvalidArgument */
        CategoryHandler::add(['name' => 'foo']); // @phpstan-ignore argument.type
    }

    public function testEditKeepsMissingValues(): void
    {
        $first = CategoryHandler::add(['parent_id' => null, 'name' => 'first']);
        $this->categoryIds[] = $first->id;
        $second = CategoryHandler::add(['parent_id' => null, 'name' => 'second']);
        $this->categoryIds[] = $second->id;

        $category = CategoryHandler::edit($second->id, 1, ['priority' => $first->priority]);

        self::assertSame('second', $category->name);
        self::assertSame($first->priority, $category->priority);

        $category = CategoryHandler::edit($second->id, 1, ['name' => 'renamed']);

        self::assertSame('renamed', $category->name);
        self::assertSame($first->priority, $category->priority);
    }

    public function testArticleAddAndEdit(): void
    {
        $category = CategoryHandler::add(['parent_id' => null, 'name' => 'category']);
        $this->categoryIds[] = $category->id;

        $first = ArticleHandler::add(['category_id' => $category->id, 'name' => 'first']);
        $second = ArticleHandler::add(['category_id' => $category->id, 'name' => 'second']);

        // the start article comes first
        self::assertSame(2, $first->priority);
        self::assertSame(3, $second->priority);

        $article = ArticleHandler::edit($second->id, 1, ['priority' => 2]);

        self::assertSame('second', $article->name);
        self::assertSame(2, $article->priority);

        ArticleHandler::delete($first->id);
        ArticleHandler::delete($second->id);
    }

    public function testArticlesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $category = $this->addCategory(null, 1);
        $a = $this->addArticle($category, 2);
        $b = $this->addArticle($category, 3);
        $c = $this->addArticle($category, 4);
        $d = $this->addArticle($category, 2);
        self::assertSame([$category, $d, $a, $b, $c], $this->articleIds($category));

        ArticleHandler::edit($c, 1, ['priority' => 2]);
        self::assertSame([$category, $c, $d, $a, $b], $this->articleIds($category));

        ArticleHandler::edit($c, 1, ['priority' => 3]);
        self::assertSame([$category, $d, $c, $a, $b], $this->articleIds($category));
    }

    public function testCategoriesAddedAndMovedWithinTheSameSecondKeepTheirOrder(): void
    {
        $parent = $this->addCategory(null, 1);
        $a = $this->addCategory($parent, 1);
        $b = $this->addCategory($parent, 2);
        $c = $this->addCategory($parent, 3);
        $d = $this->addCategory($parent, 1);
        self::assertSame([$d, $a, $b, $c], $this->categoryIds($parent));

        CategoryHandler::edit($c, 1, ['priority' => 1]);
        self::assertSame([$c, $d, $a, $b], $this->categoryIds($parent));

        CategoryHandler::edit($c, 1, ['priority' => 2]);
        self::assertSame([$d, $c, $a, $b], $this->categoryIds($parent));
    }

    private function addCategory(?int $parentId, int $priority): int
    {
        $id = CategoryHandler::add(['parent_id' => $parentId, 'name' => 'test', 'priority' => $priority])->id;
        $this->categoryIds[] = $id;

        return $id;
    }

    private function addArticle(int $categoryId, int $priority): int
    {
        $id = ArticleHandler::add(['category_id' => $categoryId, 'name' => 'test', 'priority' => $priority])->id;
        $this->articleIds[] = $id;

        return $id;
    }

    /** @return list<int> */
    private function articleIds(int $categoryId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            Sql::factory()->getArray(
                'SELECT id FROM rex_article WHERE (parent_id = :id AND id NOT IN (SELECT id FROM rex_category)) OR id = :id ORDER BY priority',
                ['id' => $categoryId],
            ),
        );
    }

    /** @return list<int> */
    private function categoryIds(int $parentId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            Sql::factory()->getArray(
                'SELECT c.id FROM rex_category c JOIN rex_article a ON a.id = c.id WHERE a.parent_id = ? ORDER BY c.priority',
                [$parentId],
            ),
        );
    }
}
