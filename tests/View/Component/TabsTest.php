<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Component\Icon;
use Redaxo\Core\View\Component\Tabs;
use Redaxo\Core\View\Component\TabsItem;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/** @internal */
final class TabsTest extends TestCase
{
    public function testRendersItemsEndItemsAndTheChildrenOfTheActiveItem(): void
    {
        $html = self::render(new Tabs(
            [
                new TabsItem('<One>', 'index.php?page=a&x=1', icon: new Icon('add')),
                new TabsItem(Html::raw('<b>Two</b>'), 'index.php?page=b', active: true, children: [
                    new TabsItem('Sub', 'index.php?page=b/sub', active: true),
                ]),
                new TabsItem('Text'),
            ],
            [new TabsItem('End', 'https://example.org', linkAttributes: new HtmlAttributes(['target' => '_blank']))],
            new HtmlAttributes(['id' => 'nav', 'aria-label' => 'Own']),
        ));

        self::assertStringStartsWith('<nav class="rex-pagenav" id="nav" aria-label="Own"><ul class="rex-tabs">', $html);
        self::assertStringContainsString('<li class="rex-tabs-item"><a href="index.php?page=a&amp;x=1" class="rex-tabs-link"><i class="rex-icon rex-icon-add" aria-hidden="true"></i> &lt;One&gt;</a></li>', $html);
        self::assertStringContainsString('<a href="index.php?page=b" class="rex-tabs-link" aria-current="page"><b>Two</b></a>', $html);
        self::assertStringContainsString('<li class="rex-tabs-item"><span class="rex-tabs-link">Text</span></li>', $html);
        self::assertStringContainsString('<li class="rex-tabs-item" data-align="end"><a href="https://example.org" class="rex-tabs-link" target="_blank">End</a></li>', $html);
        self::assertStringContainsString('<ul class="rex-subnav"><li class="rex-subnav-item"><a href="index.php?page=b/sub" class="rex-subnav-link" aria-current="page">Sub</a></li></ul>', $html);
    }

    public function testDefaultLabelAndEmptyTabs(): void
    {
        self::assertStringContainsString('aria-label="', self::render(new Tabs([new TabsItem('a', 'x')])));
        self::assertSame('', self::render(new Tabs([])));
    }

    public function testFromNavigation(): void
    {
        $item = TabsItem::fromNavigation([
            'title' => 'Media <small>pool</small>',
            'href' => 'index.php?page=mediapool',
            'active' => true,
            'icon' => 'rex-icon rex-icon-media',
            'itemClasses' => ['active', ''],
            'itemAttr' => ['class' => 'extra', 'data-x' => '1', 'empty' => ''],
            'linkClasses' => [],
            'linkAttr' => ['data-rex-overlay' => '', 'target' => '_blank'],
            'children' => [['title' => 'Upload', 'href' => 'index.php?page=mediapool/upload']],
        ]);

        self::assertSame('<i class="rex-icon rex-icon-media" aria-hidden="true"></i> Media <small>pool</small>', (string) Html::from($item->label));
        self::assertSame('index.php?page=mediapool', $item->href);
        self::assertTrue($item->active);
        self::assertSame('data-x="1" class="active extra"', $item->attributes->toString());
        self::assertSame('data-rex-overlay target="_blank"', $item->linkAttributes->toString());
        self::assertCount(1, $item->children);
        self::assertFalse($item->children[0]->active);

        self::assertNull(TabsItem::fromNavigation(['title' => 'Text', 'href' => ''])->href);
    }

    public function testFromNavigationNeedsATitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TabsItem::fromNavigation(['href' => 'x']);
    }

    private static function render(Tabs $tabs): string
    {
        return (string) preg_replace('/>\s+</', '><', trim((string) $tabs->render()));
    }
}
