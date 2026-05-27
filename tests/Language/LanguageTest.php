<?php

namespace Redaxo\Core\Tests\Language;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Language\Language;
use ReflectionClass;

/** @internal */
final class LanguageTest extends TestCase
{
    public function testGetCurrentId(): void
    {
        self::assertIsInt(Language::getCurrentId());
    }

    public function testGetId(): void
    {
        self::assertIsInt(Language::getCurrent()->id);
    }

    public function testGetPriority(): void
    {
        self::assertSame(1, Language::getCurrent()->priority);
    }

    public function testIsOnline(): void
    {
        self::assertIsBool(Language::getCurrent()->isOnline());
    }

    public function testHasValue(): void
    {
        $clang = $this->createClangWithoutConstructor();

        self::assertTrue($clang->hasValue('foo'));
        self::assertTrue($clang->hasValue('clang_foo'));

        self::assertFalse($clang->hasValue('bar'));
        self::assertFalse($clang->hasValue('clang_bar'));
    }

    public function testGetValue(): void
    {
        self::assertIsInt(Language::getCurrent()->getValue('id'));

        $clang = $this->createClangWithoutConstructor();

        self::assertEquals('teststring', $clang->getValue('foo'));
        self::assertEquals('teststring', $clang->getValue('clang_foo'));

        self::assertNull($clang->getValue('bar'));
        self::assertNull($clang->getValue('clang_bar'));
    }

    private function createClangWithoutConstructor(): Language
    {
        $reflectionClass = new ReflectionClass(Language::class);
        $language = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('additionalData')->setValue($language, ['clang_foo' => 'teststring']);

        return $language;
    }
}
