<?php

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/** @internal */
class rex_test_instance_pool_base
{
    use rex_instance_pool_trait {
        addInstance as public;
        hasInstance as public;
        getInstance as public;
        getInstancePoolKey as public;
    }

    public function __construct() {}
}

/** @internal */
final class rex_test_instance_pool_1 extends rex_test_instance_pool_base {}

/** @internal */
final class rex_test_instance_pool_2 extends rex_test_instance_pool_base {}

/** @internal */
final class rex_instance_pool_trait_test extends TestCase
{
    public function testAddHasInstance(): void
    {
        self::assertFalse(rex_test_instance_pool_1::hasInstance(1), 'hasInstance returns false for non-existing instance');
        rex_test_instance_pool_1::addInstance(1, new rex_test_instance_pool_1());
        self::assertTrue(rex_test_instance_pool_1::hasInstance(1), 'hasInstance returns true for added instance');
        self::assertFalse(rex_test_instance_pool_2::hasInstance(1), 'hasInstance uses LSB, instance is only added for subclass 1');
    }

    public function testGetInstance(): void
    {
        self::assertNull(rex_test_instance_pool_1::getInstance(2), 'getInstance returns null for non-existing key');
        $instance1 = new rex_test_instance_pool_1();
        self::assertSame($instance1, rex_test_instance_pool_1::getInstance(2, function ($id) use ($instance1) {
            $this->assertEquals(2, $id);
            return $instance1;
        }), 'getInstance returns the instance that is returned by the callback');

        $instance2 = new rex_test_instance_pool_2();
        self::assertSame($instance2, rex_test_instance_pool_2::getInstance(2, function ($id) use ($instance2) {
            $this->assertEquals(2, $id);
            return $instance2;
        }), 'getInstance uses LSB, so other instance is returned for subclass 2');

        self::assertSame($instance1, rex_test_instance_pool_1::getInstance(2), 'getInstance uses LSB, $instance1 still exists');

        rex_test_instance_pool_1::getInstance(2, function () {
            $this->fail('getInstance does not call $createCallback if instance alreays exists');
        });

        rex_test_instance_pool_1::getInstance([3, 'test'], function ($key1, $key2) {
            $this->assertEquals(3, $key1, 'getInstance passes key array as arguments to callback');
            $this->assertEquals('test', $key2, 'getInstance passes key array as arguments to callback');
        });
    }

    public function testGetInstanceCachesNullResult(): void
    {
        $calls = 0;
        $callback = static function () use (&$calls) {
            ++$calls;
            return null;
        };

        self::assertNull(rex_test_instance_pool_1::getInstance(4, $callback));
        self::assertNull(rex_test_instance_pool_1::getInstance(4, $callback));
        self::assertSame(1, $calls, 'getInstance does not call $createCallback again after it returned null');

        rex_test_instance_pool_1::clearInstance(4);
        self::assertNull(rex_test_instance_pool_1::getInstance(4, $callback));
        self::assertSame(2, $calls, 'getInstance calls $createCallback again after clearInstance()');
    }

    #[Depends('testGetInstance')]
    public function testClearInstance(): void
    {
        rex_test_instance_pool_1::clearInstance(2);
        self::assertFalse(rex_test_instance_pool_1::hasInstance(2), 'instance is cleared after clearInstance()');
        self::assertTrue(rex_test_instance_pool_2::hasInstance(2), 'clearInstance uses LSB, instance of subclass 2 still exists');
    }

    #[Depends('testClearInstance')]
    public function testClearInstancePool(): void
    {
        rex_test_instance_pool_2::clearInstancePool();
        self::assertFalse(rex_test_instance_pool_2::hasInstance(2), 'instances are cleared after clearInstancePool()');
        self::assertTrue(rex_test_instance_pool_1::hasInstance(1), 'clearInstancePool uses LSB, instances of subclass 2 still exist');
        rex_test_instance_pool_base::clearInstancePool();
        self::assertFalse(rex_test_instance_pool_1::hasInstance(1), 'baseClass::clearInstancePool clears all instances');
    }

    public function testGetInstancePoolKey(): void
    {
        self::assertEquals('1', rex_test_instance_pool_1::getInstancePoolKey(1));
        self::assertEquals('2###test', rex_test_instance_pool_1::getInstancePoolKey([2, 'test']));
    }
}
