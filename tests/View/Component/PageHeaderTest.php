<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Html;

/** @internal */
final class PageHeaderTest extends TestCase
{
    #[DataProvider('provideRender')]
    public function testRender(string $expected, PageHeader $header): void
    {
        self::assertSame($expected, (string) preg_replace(['/>\s+/', '/\s+</'], ['>', '<'], trim((string) $header->render())));
    }

    /** @return iterable<string, array{string, PageHeader}> */
    public static function provideRender(): iterable
    {
        yield 'heading only' => [
            '<header class="rex-pageheader"><div class="rex-pageheader-head"><h1 class="rex-pageheader-title">&lt;System&gt;</h1></div></header>',
            new PageHeader('<System>'),
        ];

        yield 'eyebrow and meta' => [
            '<header class="rex-pageheader"><div class="rex-pageheader-head"><h1 class="rex-pageheader-title"><span class="rex-pageheader-eyebrow">System</span><span class="rex-pageheader-separator"><span class="rex-u-visually-hidden">:</span></span><b>Settings</b></h1><p class="rex-pageheader-meta">PHP &lt;8&gt;</p></div><nav>tabs</nav></header>',
            new PageHeader(Html::raw('<b>Settings</b>'), navigation: Html::raw('<nav>tabs</nav>'), eyebrow: 'System', meta: 'PHP <8>'),
        ];
    }
}
