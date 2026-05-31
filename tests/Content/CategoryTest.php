<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use ReflectionClass;
use ReflectionProperty;

/** @internal */
final class CategoryTest extends TestCase
{
    private static int $nextId = 10000;

    public static function tearDownAfterClass(): void
    {
        Category::clearInstancePool();
    }

    public function testHasValue(): void
    {
        $instance = $this->createCategoryWithAdditionalData(['cat_foo' => 'teststring']);

        self::assertTrue($instance->hasValue('foo'));
        self::assertTrue($instance->hasValue('cat_foo'));

        self::assertFalse($instance->hasValue('bar'));
        self::assertFalse($instance->hasValue('cat_bar'));
    }

    public function testGetValue(): void
    {
        $instance = $this->createCategoryWithAdditionalData(['cat_foo' => 'teststring']);

        self::assertEquals('teststring', $instance->getValue('foo'));
        self::assertEquals('teststring', $instance->getValue('cat_foo'));

        self::assertNull($instance->getValue('bar'));
        self::assertNull($instance->getValue('cat_bar'));
    }

    #[DataProvider('dataGetClosestValue')]
    public function testGetClosestValue(string|int|null $expectedValue, Category $category): void
    {
        self::assertSame($expectedValue, $category->getClosestValue('cat_foo'));
    }

    /** @return iterable<int, array{(int|string|null), Category}> */
    public static function dataGetClosestValue(): iterable
    {
        [$lev1, $_, $lev3] = self::createCategories([], [], []);
        yield [null, $lev1];
        yield [null, $lev3];

        [$_, $_, $lev3] = self::createCategories([], [], ['cat_foo' => 'foo']);
        yield ['foo', $lev3];

        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 'bar'], ['cat_foo' => 'foo']);
        yield ['foo', $lev3];

        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 'bar'], []);
        yield ['bar', $lev3];

        [$_, $_, $lev3] = self::createCategories(['cat_foo' => 'baz'], ['cat_foo' => 'bar'], []);
        yield ['bar', $lev3];

        [$lev1, $_, $lev3] = self::createCategories(['cat_foo' => 'baz'], [], []);
        yield ['baz', $lev1];
        yield ['baz', $lev3];

        [$_, $_, $lev3] = self::createCategories([], ['cat_foo' => 0], []);
        yield [0, $lev3];
    }

    #[DataProvider('dataIsOnlineIncludingParents')]
    public function testIsOnlineIncludingParents(bool $expected, Category $category): void
    {
        self::assertSame($expected, $category->isOnlineIncludingParents());
    }

    /** @return iterable<int, array{bool, Category}> */
    public static function dataIsOnlineIncludingParents(): iterable
    {
        [$lev1, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 0], ['status' => 0]);
        yield [false, $lev1];
        yield [false, $lev3];

        [$lev1, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        yield [true, $lev1];
        yield [true, $lev3];

        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 0]);
        yield [false, $lev3];

        [$_, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 1], ['status' => 1]);
        yield [false, $lev3];

        [$_, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 2], ['status' => 1]);
        yield [false, $lev3];
    }

    #[DataProvider('dataGetClosest')]
    public function testGetClosest(?Category $expected, Category $category, callable $callback): void
    {
        self::assertSame($expected, $category->getClosest($callback));
    }

    /** @return iterable<int, array{?Category, Category, callable}> */
    public static function dataGetClosest(): iterable
    {
        $statusCallback = static fn (Category $category): bool => 1 === $category->getValue('status');

        [$lev1, $_, $lev3] = self::createCategories(['status' => 0], ['status' => 0], ['status' => 0]);
        yield [null, $lev1, $statusCallback];
        yield [null, $lev3, $statusCallback];

        [$lev1, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 1]);
        yield [$lev1, $lev1, $statusCallback];
        yield [$lev3, $lev3, $statusCallback];

        [$_, $lev2, $lev3] = self::createCategories(['status' => 1], ['status' => 1], ['status' => 0]);
        yield [$lev2, $lev3, $statusCallback];

        [$lev1, $_, $lev3] = self::createCategories(['status' => 1], ['status' => 0], ['status' => 0]);
        yield [$lev1, $lev3, $statusCallback];

        $fooCallback = static fn (Category $category): bool => $category->getValue('cat_foo') > 3;

        [$lev1, $_, $lev3] = self::createCategories(['cat_foo' => 4], [], ['cat_foo' => 2]);
        yield [$lev1, $lev3, $fooCallback];
    }

    /** @param array<string, string|int|null> $additionalData */
    private function createCategoryWithAdditionalData(array $additionalData): Category
    {
        $reflectionClass = new ReflectionClass(Category::class);
        $category = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('additionalData')->setValue($category, $additionalData);

        return $category;
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
        $lev2 = self::createCategory($lev1->id, $lev2Params);
        $lev3 = self::createCategory($lev2->id, $lev3Params);

        return [$lev1, $lev2, $lev3];
    }

    /** @param array<string, string|int|null> $params */
    private static function createCategory(?int $parentId, array $params): Category
    {
        $id = self::$nextId++;
        $status = isset($params['status']) ? (int) $params['status'] : 1;
        unset($params['status']);

        $reflectionClass = new ReflectionClass(Category::class);
        $category = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('id')->setValue($category, $id);
        $reflectionClass->getProperty('parentId')->setValue($category, $parentId);
        $reflectionClass->getProperty('clangId')->setValue($category, 1);
        $reflectionClass->getProperty('status')->setValue($category, $status);
        $reflectionClass->getProperty('path')->setValue($category, []);
        $reflectionClass->getProperty('additionalData')->setValue($category, $params);

        // register in instance pool so Category::get($id, 1) returns this instance
        $instancesProperty = new ReflectionProperty(StructureElement::class, 'instances');
        /** @var array<class-string, array<string, ?Category>> $instances */
        $instances = $instancesProperty->getValue();
        $instances[Category::class][$id . '###1'] = $category;
        $instancesProperty->setValue(null, $instances);

        return $category;
    }
}
