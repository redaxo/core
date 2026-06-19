<?php

namespace Redaxo\Core\Tests\View;

use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Environment;
use Redaxo\Core\View\HtmlAttributes;

/**
 * @internal
 */
final class HtmlAttributesTest extends TestCase
{
    public function testValuesAreEscaped(): void
    {
        // the security-critical bit: values (plain and inside arrays) are escaped, names are not
        // touched because they are typed as literal-string and therefore never user input
        $attributes = new HtmlAttributes([
            'title' => '"><script>',
            'class' => ['"><x'],
        ]);

        self::assertSame('title="&quot;&gt;&lt;script&gt;" class="&quot;&gt;&lt;x"', $attributes->toString());
    }

    /** @param array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null> $attributes */
    #[DataProvider('dataConstruct')]
    public function testConstruct(string $expected, array $attributes): void
    {
        $attributes = new HtmlAttributes($attributes);

        self::assertSame($expected, $attributes->toString());
    }

    /** @return list<array{string, array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null>}> */
    public static function dataConstruct(): array
    {
        return [
            ['', []],
            ['', ['foo' => null, 'bar' => false, 'baz' => []]],
            [
                'title="foo" maxlength="5" disabled data-environment="backend"',
                ['title' => 'foo', 'maxlength' => 5, 'disabled' => true, 'data-environment' => Environment::Backend],
            ],
            [
                'title="foo" class="cls1 cls3 cls4"',
                ['title' => 'foo', 'value' => null, 'class' => [
                    'cls1',
                    'cls2' => false,
                    'cls3',
                    'cls4' => true,
                ]],
            ],
            [
                'data-environments="backend frontend"',
                ['data-environments' => [Environment::Backend, Environment::Frontend]],
            ],
        ];
    }

    /**
     * @param array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null> $initial
     * @param array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null> $with
     */
    #[DataProvider('dataWith')]
    public function testWith(string $expected, array $initial, array $with): void
    {
        $initial = new HtmlAttributes($initial);
        $with = $initial->with($with);

        self::assertNotSame($initial, $with);
        self::assertSame($expected, $with->toString());
    }

    /** @return list<array{string, array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null>, array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null>}> */
    public static function dataWith(): array
    {
        return [
            ['', [], []],
            [
                '',
                ['foo' => 'bar', 'disabled' => true],
                ['foo' => null, 'disabled' => false],
            ],
            [
                'foo="bar" disabled',
                ['foo' => null, 'disabled' => false],
                ['foo' => 'bar', 'disabled' => true],
            ],
            [
                'title="foo" value="5"',
                ['value' => 5],
                ['title' => 'foo'],
            ],
            [
                'class="cls1 cls3" value="5"',
                ['value' => 5],
                ['class' => ['cls1', 'cls2' => false, 'cls3' => true]],
            ],
            [
                'class="cls1 cls3 foo bar"',
                ['class' => 'foo cls2 bar'],
                ['class' => ['cls1', 'cls2' => false, 'cls3' => true]],
            ],
            [
                'class="cls1 cls3 foo bar"',
                ['class' => ['cls1' => false, 'cls2', 'foo', 'cls3', 'bar' => true, 'baz' => false]],
                ['class' => ['cls1', 'cls2' => false, 'cls3' => true]],
            ],
            [
                'class="cls1 cls2"',
                ['class' => ['cls1' => false, 'foo', 'bar' => true]],
                ['class' => 'cls1 cls2'],
            ],
            [
                'class="cls1 cls2"',
                ['class' => 'cls1 foo bar'],
                ['class' => 'cls1 cls2'],
            ],
            [
                'data-environments="frontend backend"',
                ['data-environments' => Environment::Backend],
                ['data-environments' => [Environment::Frontend]],
            ],
            [
                'data-environments="backend frontend console"',
                ['data-environments' => [Environment::Frontend, Environment::Backend]],
                ['data-environments' => [Environment::Backend, Environment::Frontend, Environment::Console]],
            ],
        ];
    }
}
