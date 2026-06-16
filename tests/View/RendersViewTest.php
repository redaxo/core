<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Tests\View\Fixtures\Card;
use Redaxo\Core\Tests\View\Fixtures\Greeting;
use Redaxo\Core\View\Html;

/** @internal */
final class RendersViewTest extends TestCase
{
    public function testRenderablePropertyAcceptsAComponentDirectly(): void
    {
        // a sub-component is passed as content without calling ->render() on it first
        $html = (string) new Greeting(name: 'Outer', message: new Card(title: 'Inner'))->render();

        self::assertStringContainsString('Hello, Outer!', $html);
        self::assertStringContainsString('class="card"', $html);
        self::assertStringContainsString('Inner', $html);
    }

    public function testRendersCoLocatedViewAndEscapesByType(): void
    {
        $html = (string) new Greeting(name: '<script>', message: Html::raw('<em>hi</em>'))->render();

        self::assertStringContainsString('Hello, &lt;script&gt;!', $html); // plain string escaped in the view
        self::assertStringContainsString('<em>hi</em>', $html);            // Html emitted as-is
    }

    public function testPlainStringContentIsEscapedInTheView(): void
    {
        $html = (string) new Greeting(name: 'x', message: '<b>unsafe</b>')->render();

        self::assertStringContainsString('&lt;b&gt;unsafe&lt;/b&gt;', $html);
    }

    public function testInlineHtmlCanBePassedViaCapture(): void
    {
        $message = Html::capture(static function (): void { ?>
            <span class="foo">inline</span>
        <?php });

        $html = (string) new Greeting(name: 'x', message: $message)->render();

        self::assertStringContainsString('<span class="foo">inline</span>', $html);
    }
}
