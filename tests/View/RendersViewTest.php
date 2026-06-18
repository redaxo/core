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

    public function testUnfilledOptionalContentFallsBackToInlineDefault(): void
    {
        $html = (string) new Card(title: 'Hi')->render();

        self::assertStringContainsString('No content yet.', $html); // inline default from the view
        self::assertStringNotContainsString('card-footer', $html);  // optional content omitted entirely
    }

    public function testOptionalContentIsFilledWithNamedArguments(): void
    {
        $html = (string) new Card(
            title: 'Hi',
            body: Html::capture(static function (): void { ?>
                <p>Real body</p>
            <?php }),
            footer: Html::raw('<small>foot</small>'),
        )->render();

        self::assertStringContainsString('<p>Real body</p>', $html);
        self::assertStringNotContainsString('No content yet.', $html);
        self::assertStringContainsString('<small>foot</small>', $html);
    }

    public function testStringContentOnAStringOrRenderablePropertyIsEscaped(): void
    {
        // Card::$footer is string|Renderable|null — a plain string goes through Html::from() and is escaped
        $html = (string) new Card(title: 'Hi', footer: '<script>alert(1)</script>')->render();

        self::assertStringContainsString('<footer class="card-footer">', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)', $html);
    }
}
