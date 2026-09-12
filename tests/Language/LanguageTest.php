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
        $language = $this->createLanguageWithoutConstructor();

        self::assertTrue($language->hasValue('foo'));
        self::assertTrue($language->hasValue('lang_foo'));

        self::assertFalse($language->hasValue('bar'));
        self::assertFalse($language->hasValue('lang_bar'));
    }

    public function testGetValue(): void
    {
        self::assertIsInt(Language::getCurrent()->getValue('id'));

        $language = $this->createLanguageWithoutConstructor();

        self::assertEquals('teststring', $language->getValue('foo'));
        self::assertEquals('teststring', $language->getValue('lang_foo'));

        self::assertNull($language->getValue('bar'));
        self::assertNull($language->getValue('lang_bar'));
    }

    private function createLanguageWithoutConstructor(): Language
    {
        $reflectionClass = new ReflectionClass(Language::class);
        $language = $reflectionClass->newInstanceWithoutConstructor();

        $reflectionClass->getProperty('additionalData')->setValue($language, ['lang_foo' => 'teststring']);

        return $language;
    }
}
