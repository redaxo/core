<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Fragment;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;

/** @internal */
final class GridFragmentTest extends TestCase
{
    public function testSingleContentIsOutputWithoutGrid(): void
    {
        self::assertSame('<p>a</p>', self::parse(['content' => ['<p>a</p>']]));
        self::assertSame('<p>a</p>', self::parse(['content' => '<p>a</p>']));
    }

    public function testEmptyContent(): void
    {
        self::assertSame('', self::parse(['content' => []]));
        self::assertSame('', self::parse([]));
        self::assertSame('<div class="rex-grid" data-layout="split" data-from="md"><div></div><div>b</div></div>', self::parse(['content' => [null, 'b']]));
    }

    public function testWithoutSpansTheColumnsShareOneRowFromMd(): void
    {
        self::assertSame(
            '<div class="rex-grid" data-layout="split" data-from="md"><div><p>a</p></div><div><p>b</p></div></div>',
            self::parse(['content' => ['<p>a</p>', '<p>b</p>']]),
        );
    }

    public function testSpans(): void
    {
        self::assertSame(
            '<div class="rex-grid"><div data-span="12 md:8">a</div><div data-span="md:4 xl:6">b</div><div>c</div></div>',
            self::parse(['content' => ['a', 'b', 'c'], 'spans' => ['md:8 12', 'md:4 xl:6']]),
        );
    }

    public function testSpansFollowTheKeysOfTheContent(): void
    {
        self::assertSame(
            '<div class="rex-grid"><div data-span="md:8">main</div><div data-span="md:4">side</div></div>',
            self::parse(['content' => ['main' => 'main', 'side' => 'side'], 'spans' => ['side' => 'md:4', 'main' => 'md:8']]),
        );
        self::assertSame(
            '<div class="rex-grid"><div data-span="md:8">a</div><div data-span="md:4">b</div></div>',
            self::parse(['content' => [1 => 'a', 3 => 'b'], 'spans' => [1 => 'md:8', 3 => 'md:4']]),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidSpan(): iterable
    {
        yield 'dash' => ['md-6'];
        yield 'zero' => ['0'];
        yield 'thirteen' => ['13'];
        yield 'xs prefix' => ['xs:6'];
        yield 'keyword' => ['md:auto'];
        yield 'same breakpoint twice' => ['md:6 md:8'];
    }

    #[DataProvider('provideInvalidSpan')]
    public function testInvalidSpan(string $span): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::parse(['content' => ['a', 'b'], 'spans' => [$span, 'md:6']]);
    }

    public function testClassesAreDeprecated(): void
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $message) use (&$messages): bool {
            // a deprecation silenced with "@" would be discarded by the error handler of REDAXO; PHPUnit lowers
            // error_reporting() during the test, but "@" leaves only the levels that can't be silenced
            if (error_reporting() & ~(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE)) {
                $messages[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);

        try {
            self::parse(['content' => ['a', 'b'], 'classes' => ['col-sm-6', 'col-sm-6']]);
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $messages);
        self::assertStringContainsString('"classes"', $messages[0]);
    }

    /** @param array<string, string|array<array-key, string|null>> $vars */
    private static function parse(array $vars): string
    {
        // the fragment is the deprecated way to the grid, kept as adapter for addons
        $fragment = new Fragment(); // @phpstan-ignore new.deprecatedClass
        foreach ($vars as $name => $value) {
            $fragment->setVar($name, $value, false);
        }

        return (string) preg_replace('/>\s+</', '><', trim($fragment->parse('core/page/grid.php')));
    }
}
