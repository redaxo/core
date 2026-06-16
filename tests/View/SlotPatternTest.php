<?php

namespace Redaxo\Core\Tests\View;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Tests\View\Fixtures\Card;
use Redaxo\Core\View\Html;

/**
 * Demonstrates that "slots" (named content regions, with defaults, filled by the caller) are covered
 * by typed optional {@see Html} properties + named arguments — no dedicated slot runtime needed.
 *
 * @internal
 */
final class SlotPatternTest extends TestCase
{
    public function testUnfilledSlotFallsBackToInlineDefault(): void
    {
        $html = (string) new Card(title: 'Hi')->render();

        self::assertStringContainsString('No content yet.', $html); // inline default from the view
        self::assertStringNotContainsString('card-footer', $html);  // optional slot omitted entirely
    }

    public function testCallerFillsSlotsWithNamedArguments(): void
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
}
