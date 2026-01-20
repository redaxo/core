<?php

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_navigation_test extends TestCase
{
    public function testContruct(): void
    {
        $this->expectException(rex_exception::class);
        $this->expectExceptionMessage('Base class rex_navigation must be instantiated via rex_navigation::factory().');

        new rex_navigation();
    }

    public function testFactory(): void
    {
        $nav = rex_navigation::factory();

        self::assertInstanceOf(rex_navigation::class, $nav);
    }

    #[DoesNotPerformAssertions]
    public function testConstructAnonymousClass(): void
    {
        // no exception
        new class extends rex_navigation {};
    }
}
