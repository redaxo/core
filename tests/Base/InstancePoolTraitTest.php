<?php

namespace Redaxo\Core\Tests\Base;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Base\InstancePoolTrait;

/** @internal */
class TestInstancePoolBase
{
    use InstancePoolTrait {
        addInstance as public;
        hasInstance as public;
        getInstance as public;
        getInstancePoolKey as public;
    }

    public function __construct() {}
}

/** @internal */
final class TestInstancePool1 extends TestInstancePoolBase {}

/** @internal */
final class TestInstancePool2 extends TestInstancePoolBase {}

/** @internal */
final class InstancePoolTraitTest extends TestCase
{
    public function testAddHasInstance(): void
    {
        self::assertFalse(TestInstancePool1::hasInstance(1), 'hasInstance returns false for non-existing instance');
        TestInstancePool1::addInstance(1, new TestInstancePool1());
        self::assertTrue(TestInstancePool1::hasInstance(1), 'hasInstance returns true for added instance');
        self::assertFalse(TestInstancePool2::hasInstance(1), 'hasInstance uses LSB, instance is only added for subclass 1');
    }

    public function testGetInstance(): void
    {
        self::assertNull(TestInstancePool1::getInstance(2), 'getInstance returns null for non-existing key');
        $instance1 = new TestInstancePool1();
        self::assertSame($instance1, TestInstancePool1::getInstance(2, static function () use ($instance1) {
            return $instance1;
        }), 'getInstance returns the instance that is returned by the callback');

        $instance2 = new TestInstancePool2();
        self::assertSame($instance2, TestInstancePool2::getInstance(2, static function () use ($instance2) {
            return $instance2;
        }), 'getInstance uses LSB, so other instance is returned for subclass 2');

        self::assertSame($instance1, TestInstancePool1::getInstance(2), 'getInstance uses LSB, $instance1 still exists');

        TestInstancePool1::getInstance(2, function () {
            $this->fail('getInstance does not call $createCallback if instance alreays exists');
        });
    }

    public function testGetInstanceCachesNullResult(): void
    {
        $callback = static fn () => null;

        self::assertNull(TestInstancePool1::getInstance(4, $callback));
        self::assertNull(TestInstancePool1::getInstance(4, function () {
            $this->fail('getInstance does not call $createCallback again after it returned null');
        }));

        TestInstancePool1::clearInstance(4);

        $called = false;
        self::assertNull(TestInstancePool1::getInstance(4, static function () use (&$called) {
            $called = true;
            return null;
        }));
        self::assertTrue($called, 'getInstance calls $createCallback again after clearInstance()');
    }

    #[Depends('testGetInstance')]
    public function testClearInstance(): void
    {
        TestInstancePool1::clearInstance(2);
        self::assertFalse(TestInstancePool1::hasInstance(2), 'instance is cleared after clearInstance()');
        self::assertTrue(TestInstancePool2::hasInstance(2), 'clearInstance uses LSB, instance of subclass 2 still exists');
    }

    #[Depends('testClearInstance')]
    public function testClearInstancePool(): void
    {
        TestInstancePool2::clearInstancePool();
        self::assertFalse(TestInstancePool2::hasInstance(2), 'instances are cleared after clearInstancePool()');
        self::assertTrue(TestInstancePool1::hasInstance(1), 'clearInstancePool uses LSB, instances of subclass 2 still exist');
        TestInstancePoolBase::clearInstancePool();
        self::assertFalse(TestInstancePool1::hasInstance(1), 'baseClass::clearInstancePool clears all instances');
    }

    public function testGetInstancePoolKey(): void
    {
        self::assertEquals('1', TestInstancePool1::getInstancePoolKey(1));
        self::assertEquals('2###test', TestInstancePool1::getInstancePoolKey([2, 'test']));
    }
}
