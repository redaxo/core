<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;

/** @internal */
final class HtmlTest extends TestCase
{
    public function testFromEscapesPlainString(): void
    {
        self::assertSame('&lt;b&gt;x&lt;/b&gt;', (string) Html::from('<b>x</b>'));
    }

    public function testRenderReturnsItself(): void
    {
        $html = Html::raw('<b>x</b>');

        self::assertSame($html, $html->render());
    }

    public function testFromRendersARenderable(): void
    {
        $renderable = new class implements Renderable {
            public function render(): Html
            {
                return Html::raw('<i>rendered</i>');
            }
        };

        self::assertSame('<i>rendered</i>', (string) Html::from($renderable));
    }

    public function testFromPassesExistingHtmlThrough(): void
    {
        $html = Html::raw('<b>x</b>');

        self::assertSame($html, Html::from($html));
    }

    public function testRawKeepsMarkupUnescaped(): void
    {
        self::assertSame('<b>x</b>', (string) Html::raw('<b>x</b>'));
    }

    public function testCaptureWrapsClosureOutput(): void
    {
        $html = Html::capture(static function (): void {
            echo '<span class="foo">hi</span>';
        });

        self::assertSame('<span class="foo">hi</span>', (string) $html);
    }
}
