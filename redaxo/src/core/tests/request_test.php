<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_request_test extends TestCase
{
    #[DataProvider('dataArrayKeyCast')]
    public function testArrayKeyCast(mixed $expected, mixed $value, mixed $vartype, mixed $default = ''): void
    {
        $method = new ReflectionMethod(rex_request::class, 'arrayKeyCast');

        $haystack = null === $value ? [] : ['varname' => $value];

        self::assertSame($expected, $method->invoke(null, $haystack, 'varname', $vartype, $default));
    }

    /** @return list<array{0: mixed, 1: mixed, 2: mixed, 3?: mixed}> */
    public static function dataArrayKeyCast(): array
    {
        return [
            [0, null, 'int'],
            [null, null, 'int', null],
            ['foo', null, 'int', 'foo'],
            ['foo', 'foo', 'string', 'bar'],
            ['', ['foo', 'bar'], 'string', 'qux'], // https://github.com/redaxo/core/issues/2900
            ['foo', 'x', ['foo', 'bar']],
            ['baz', 'x', ['foo', 'bar'], 'baz'],
            [null, 'x', ['foo', 'bar'], null],
        ];
    }
}
