<?php

namespace Redaxo\Core\Tests\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Content\ArticleContentBase;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use ReflectionClass;
use ReflectionProperty;

/** @internal */
final class ArticleContentBaseTest extends TestCase
{
    public function testHasValue(): void
    {
        $instance = $this->createArticleContentBaseWithoutConstructor();

        // fake meta field in database structure
        $propArticle = new ReflectionProperty(ArticleContentBase::class, 'articleSql');
        $propArticle->setValue($instance, Sql::factory()->setValue('art_foo', 'teststring'));

        self::assertTrue($instance->hasValue('foo'));
        self::assertTrue($instance->hasValue('art_foo'));

        self::assertFalse($instance->hasValue('bar'));
        self::assertFalse($instance->hasValue('art_bar'));
    }

    public function testGetValue(): void
    {
        $instance = $this->createArticleContentBaseWithoutConstructor();

        // fake meta field in database structure
        $propArticle = new ReflectionProperty(ArticleContentBase::class, 'articleSql');
        $propArticle->setValue($instance, Sql::factory()->setValue('art_foo', 'teststring'));

        self::assertEquals('teststring', $instance->getValue('foo'));
        self::assertEquals('teststring', $instance->getValue('art_foo'));
    }

    #[DataProvider('dataGetValueNonExisting')]
    public function testGetValueNonExisting(string $value): void
    {
        $instance = $this->createArticleContentBaseWithoutConstructor();

        $this->expectException(LogicException::class);

        $instance->getValue($value);
    }

    /** @return list<array{string}> */
    public static function dataGetValueNonExisting(): array
    {
        return [
            ['bar'],
            ['art_bar'],
        ];
    }

    private function createArticleContentBaseWithoutConstructor(): ArticleContentBase
    {
        return new ReflectionClass(ArticleContentBase::class)->newInstanceWithoutConstructor();
    }
}
