<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use ReflectionClass;
use ReflectionProperty;

/** @internal */
final class ArticleTest extends TestCase
{
    private static int $nextId = 20000;

    public static function tearDownAfterClass(): void
    {
        Article::clearInstancePool();
        // Categories created here are left in the pool — clearing them would break
        // CategoryTest, which already populated its own pool entries via data providers
        // and runs after ArticleTest alphabetically.
    }

    public function testHasValue(): void
    {
        $instance = $this->createArticleWithAdditionalData(['art_foo' => 'teststring']);

        self::assertTrue($instance->hasValue('foo'));
        self::assertTrue($instance->hasValue('art_foo'));

        self::assertFalse($instance->hasValue('bar'));
        self::assertFalse($instance->hasValue('art_bar'));
    }

    public function testGetValue(): void
    {
        $instance = $this->createArticleWithAdditionalData(['art_foo' => 'teststring']);

        self::assertEquals('teststring', $instance->getValue('foo'));
        self::assertEquals('teststring', $instance->getValue('art_foo'));

        self::assertNull($instance->getValue('bar'));
        self::assertNull($instance->getValue('art_bar'));
    }

    /** @param callable(StructureElement):bool $callback */
    #[DataProvider('dataGetClosest')]
    public function testGetClosest(?StructureElement $expected, Article $article, callable $callback): void
    {
        self::assertSame($expected, $article->getClosest($callback));
    }

    /** @return iterable<int, array{?StructureElement, Article, callable(StructureElement):bool}> */
    public static function dataGetClosest(): iterable
    {
        $statusCallback = static fn (StructureElement $el): bool => 1 === $el->getValue('status');

        // online article in online tree → article itself
        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        $article = self::createArticle($lev3, ['status' => 1]);
        yield [$article, $article, $statusCallback];

        // offline article, online container category → category
        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        $article = self::createArticle($lev3, ['status' => 0]);
        yield [$lev3, $article, $statusCallback];

        // offline article + offline container, online grandparent → grandparent
        [$lev1, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 0], ['status' => 0]);
        $article = self::createArticle($lev3, ['status' => 0]);
        yield [$lev1, $article, $statusCallback];

        // nothing matches → null
        [$_, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 0], ['status' => 0]);
        $article = self::createArticle($lev3, ['status' => 0]);
        yield [null, $article, $statusCallback];

        // meta-info value found via metaInfoPrefix lookup: art_foo on article, cat_foo on cats
        $fooCallback = static fn (StructureElement $el): bool => $el->getValue('foo') > 3;
        [$lev1, $_, $lev3] = self::createCategories(['cat_foo' => 4], [], ['cat_foo' => 2]);
        $article = self::createArticle($lev3, ['art_foo' => 1]);
        yield [$lev1, $article, $fooCallback];

        // article's own art_foo matches first
        [$_, $_, $lev3] = self::createCategories(['cat_foo' => 4], [], ['cat_foo' => 2]);
        $article = self::createArticle($lev3, ['art_foo' => 5]);
        yield [$article, $article, $fooCallback];
    }

    #[DataProvider('dataGetClosestValue')]
    public function testGetClosestValue(string|int|null $expectedValue, Article $article): void
    {
        self::assertSame($expectedValue, $article->getClosestValue('foo'));
    }

    /** @return iterable<int, array{(int|string|null), Article}> */
    public static function dataGetClosestValue(): iterable
    {
        [$_, $_, $lev3] = self::createCategories([], [], []);
        yield [null, self::createArticle($lev3, [])];

        // article's own art_foo wins over everything else in the cat tree
        [$_, $_, $lev3] = self::createCategories(['cat_foo' => 'baz'], ['cat_foo' => 'bar'], ['cat_foo' => 'foo']);
        yield ['from-article', self::createArticle($lev3, ['art_foo' => 'from-article'])];

        // no art_foo on article — falls through to cat_foo on containing cat
        [$_, $_, $lev3] = self::createCategories([], [], ['cat_foo' => 'foo']);
        yield ['foo', self::createArticle($lev3, [])];

        // direct cat wins over grandparents
        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 'bar'], ['cat_foo' => 'foo']);
        yield ['foo', self::createArticle($lev3, [])];

        // walks up to nearest cat with value
        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 'bar'], []);
        yield ['bar', self::createArticle($lev3, [])];

        [$_, $_, $lev3] = self::createCategories(['cat_foo' => 'baz'], [], []);
        yield ['baz', self::createArticle($lev3, [])];

        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 0], []);
        yield [0, self::createArticle($lev3, [])];

        // start-articles fall through to their parent category — own cat (lev3) is skipped
        [$_, $_, $lev3] = self::createCategories(['cat_foo' => 'baz'], ['cat_foo' => 'bar'], ['cat_foo' => 'foo']);
        yield ['bar', self::createArticle($lev3, [], startArticle: true)];
    }

    #[DataProvider('dataIsOnlineIncludingParents')]
    public function testIsOnlineIncludingParents(bool $expected, Article $article): void
    {
        self::assertSame($expected, $article->isOnlineIncludingParents());
    }

    /** @return iterable<int, array{bool, Article}> */
    public static function dataIsOnlineIncludingParents(): iterable
    {
        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        yield [true, self::createArticle($lev3, ['status' => 1])];

        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        yield [false, self::createArticle($lev3, ['status' => 0])];

        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 0]);
        yield [false, self::createArticle($lev3, ['status' => 1])];

        [$_, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 1], ['status' => 1]);
        yield [false, self::createArticle($lev3, ['status' => 1])];

        // start-article in an offline parent category
        [$_, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 1], ['status' => 1]);
        yield [false, self::createArticle($lev3, ['status' => 1], startArticle: true)];
    }

    /** @param array<string, string|int|null> $additionalData */
    private function createArticleWithAdditionalData(array $additionalData): Article
    {
        $reflectionClass = new ReflectionClass(Article::class);
        $article = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('additionalData')->setValue($article, $additionalData);

        return $article;
    }

    /**
     * @param array<string, string|int|null> $lev1Params
     * @param array<string, string|int|null> $lev2Params
     * @param array<string, string|int|null> $lev3Params
     * @return array{Category, Category, Category}
     */
    private static function createCategories(array $lev1Params, array $lev2Params, array $lev3Params): array
    {
        $lev1 = self::createCategory(null, $lev1Params);
        $lev2 = self::createCategory($lev1, $lev2Params);
        $lev3 = self::createCategory($lev2, $lev3Params);

        return [$lev1, $lev2, $lev3];
    }

    /** @param array<string, string|int|null> $params */
    private static function createCategory(?Category $parent, array $params): Category
    {
        $id = self::$nextId++;
        $status = isset($params['status']) ? (int) $params['status'] : 1;
        unset($params['status']);

        $reflectionClass = new ReflectionClass(Category::class);
        $category = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('id')->setValue($category, $id);
        $reflectionClass->getProperty('parentId')->setValue($category, $parent?->id);
        $reflectionClass->getProperty('clangId')->setValue($category, 1);
        $reflectionClass->getProperty('status')->setValue($category, $status);
        $reflectionClass->getProperty('path')->setValue($category, []);
        $reflectionClass->getProperty('additionalData')->setValue($category, $params);

        self::registerInstance(Category::class, $id, $category);

        return $category;
    }

    /** @param array<string, string|int|null> $params */
    private static function createArticle(Category $category, array $params, bool $startArticle = false): Article
    {
        $id = self::$nextId++;
        $status = isset($params['status']) ? (int) $params['status'] : 1;
        unset($params['status']);

        $reflectionClass = new ReflectionClass(Article::class);
        $article = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('id')->setValue($article, $id);
        $reflectionClass->getProperty('clangId')->setValue($article, 1);
        $reflectionClass->getProperty('status')->setValue($article, $status);
        $reflectionClass->getProperty('path')->setValue($article, []);
        $reflectionClass->getProperty('categoryId')->setValue($article, $category->id);
        $reflectionClass->getProperty('startArticle')->setValue($article, $startArticle);
        $reflectionClass->getProperty('additionalData')->setValue($article, $params);

        self::registerInstance(Article::class, $id, $article);

        return $article;
    }

    /** @param class-string $class */
    private static function registerInstance(string $class, int $id, StructureElement $instance): void
    {
        $instancesProperty = new ReflectionProperty(StructureElement::class, 'instances');
        /** @var array<class-string, array<string, ?StructureElement>> $instances */
        $instances = $instancesProperty->getValue();
        $instances[$class][$id . '###1'] = $instance;
        $instancesProperty->setValue(null, $instances);
    }
}
