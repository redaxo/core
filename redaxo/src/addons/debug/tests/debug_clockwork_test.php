<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_debug_clockwork_test extends TestCase
{
    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $defaults
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('provideGetChangedInputValues')]
    public function testGetChangedInputValues(array $values, array $defaults, array $expected): void
    {
        self::assertSame($expected, rex_debug_clockwork::getChangedInputValues($values, $defaults));
    }

    /** @return iterable<string, array{array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>}> */
    public static function provideGetChangedInputValues(): iterable
    {
        yield 'defaults only' => [
            ['command' => 'cache:clear', 'quiet' => false, 'env' => null],
            ['command' => 'cache:clear', 'quiet' => false, 'env' => null],
            [],
        ];
        yield 'changed scalar' => [
            ['quiet' => true, 'env' => 'prod'],
            ['quiet' => false, 'env' => null],
            ['quiet' => true, 'env' => 'prod'],
        ];
        yield 'option with several values' => [
            ['apply' => false, 'drop' => ['rex_old'], 'keep' => []],
            ['apply' => false, 'drop' => [], 'keep' => []],
            ['drop' => ['rex_old']],
        ];
        yield 'compared with the default of the same option' => [
            ['format' => 'json', 'output' => 'txt'],
            ['format' => 'txt', 'output' => 'txt'],
            ['format' => 'json'],
        ];
        yield 'without default' => [
            ['extra' => 'value'],
            [],
            ['extra' => 'value'],
        ];
    }
}
