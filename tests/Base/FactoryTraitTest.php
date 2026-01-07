<?php

namespace Redaxo\Core\Tests\Base;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Base\FactoryTrait;

/**
 * @internal
 * @psalm-consistent-constructor
 */
class TestFactory
{
    use FactoryTrait;

    /** @return class-string<self> */
    public static function factory(): string
    {
        // just return the class which was determined using FactoryTrait.
        // this doesn't make sense in real use-cases but eases testing
        return self::getFactoryClass();
    }

    public function doSomething(): string
    {
        return 'base';
    }

    public static function staticCall(): string
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            return $factoryClass::staticCall();
        }
        return 'static-base';
    }
}

/** @internal */
final class AlternativeTestFactory extends TestFactory
{
    #[Override]
    public function doSomething(): string
    {
        return 'overridden';
    }

    #[Override]
    public static function staticCall(): string
    {
        return 'static-overridden';
    }
}

/** @internal */
final class FactoryTraitTest extends TestCase
{
    public function testFactoryCreation(): void
    {
        self::assertFalse(TestFactory::hasFactoryClass(), 'initially no factory class is set');
        self::assertEquals(TestFactory::class, TestFactory::getFactoryClass(), 'original factory class will be returned');
        $clazz = TestFactory::factory();
        self::assertEquals(TestFactory::class, $clazz, 'factory class defaults to the original impl');
        $obj = new $clazz();
        self::assertEquals('base', $obj->doSomething(), 'call method of the original impl');
        self::assertEquals('static-base', TestFactory::staticCall(), 'static method of original impl');

        TestFactory::setFactoryClass(AlternativeTestFactory::class);

        self::assertTrue(TestFactory::hasFactoryClass(), 'factory class was set');
        self::assertEquals(AlternativeTestFactory::class, TestFactory::getFactoryClass(), 'factory class will be returned');
        $clazz = TestFactory::factory();
        self::assertEquals(AlternativeTestFactory::class, $clazz, 'alternative factory class will be used');
        $obj = new $clazz();
        self::assertEquals('overridden', $obj->doSomething(), 'call method of the alternative impl');
        self::assertEquals('static-overridden', TestFactory::staticCall(), 'static method of alternative impl');
    }
}
