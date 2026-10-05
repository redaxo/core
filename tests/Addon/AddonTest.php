<?php

namespace Redaxo\Core\Tests\Addon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Debug\DebugAddon;
use Redaxo\Test\TestAddon;

use function sprintf;

/** @internal */
final class AddonTestUnregisteredAddon extends Addon {}

/** @internal */
final class AddonTest extends TestCase
{
    public function testInstance(): void
    {
        self::assertSame(Addon::require('test'), TestAddon::instance());
        self::assertSame(Addon::require('debug'), DebugAddon::instance());
    }

    /** @param class-string<Addon> $class */
    #[DataProvider('provideInstanceOfUnregisteredClass')]
    public function testInstanceOfUnregisteredClass(string $class): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Class "%s" is not registered as an addon class.', $class));

        $class::instance();
    }

    /** @return iterable<int, array{class-string<Addon>}> */
    public static function provideInstanceOfUnregisteredClass(): iterable
    {
        yield [Addon::class];
        yield [AddonTestUnregisteredAddon::class];
    }
}
