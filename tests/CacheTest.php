<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Cache;

/** @internal */
class CacheTestPoolBase
{
    use InstanceListPoolTrait {
        addInstanceList as public;
        hasInstanceList as public;
    }
    use InstancePoolTrait {
        addInstance as public;
        hasInstance as public;
    }

    public function __construct() {}
}

/** @internal */
final class CacheTestPoolChild extends CacheTestPoolBase {}

/** @internal */
final class CacheTest extends TestCase
{
    // Separate process, as the reset would also wipe the pooled fixtures other tests set up in their data providers
    #[RunInSeparateProcess]
    public function testResetClearsInstancePools(): void
    {
        CacheTestPoolBase::addInstance(1, new CacheTestPoolBase());
        CacheTestPoolChild::addInstance(1, new CacheTestPoolChild());
        CacheTestPoolBase::addInstanceList(1, [1]);

        Cache::reset();

        self::assertFalse(CacheTestPoolBase::hasInstance(1));
        self::assertFalse(CacheTestPoolChild::hasInstance(1));
        self::assertFalse(CacheTestPoolBase::hasInstanceList(1));
    }
}
