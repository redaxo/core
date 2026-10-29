<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Fragment;

use const E_USER_DEPRECATED;

/** @internal */
final class GridFragmentTest extends TestCase
{
    public function testSingleContentIsOutputWithoutGrid(): void
    {
        self::assertSame('<p>a</p>', trim(self::parse(['content' => ['<p>a</p>']])));
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
            '<div class="rex-grid"><div data-span="12 md:8">a</div><div data-span="md:4">b</div><div>c</div></div>',
            self::parse(['content' => ['a', 'b', 'c'], 'spans' => ['md:8 12', 'md:4']]),
        );
    }

    public function testInvalidSpan(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::parse(['content' => ['a', 'b'], 'spans' => ['md-6', 'md:6']]);
    }

    public function testClassesAreDeprecated(): void
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $message) use (&$messages): bool {
            $messages[] = $message;

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

    /** @param array<string, list<string>> $vars */
    private static function parse(array $vars): string
    {
        $fragment = new Fragment();
        foreach ($vars as $name => $value) {
            $fragment->setVar($name, $value, false);
        }

        return (string) preg_replace('/>\s+</', '><', trim($fragment->parse('core/page/grid.php')));
    }
}
