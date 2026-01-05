<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\View\Navigation;

/** @internal */
final class NavigationTest extends TestCase
{
    public function testContruct(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Base class ' . Navigation::class . ' must be instantiated via ' . Navigation::class . '::factory().');

        new Navigation();
    }

    public function testFactory(): void
    {
        $nav = Navigation::factory();

        self::assertInstanceOf(Navigation::class, $nav); // @phpstan-ignore-line
    }

    #[DoesNotPerformAssertions]
    public function testConstructAnonymousClass(): void
    {
        // no exception
        new class extends Navigation {};
    }
}
