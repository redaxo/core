<?php

namespace Redaxo\Core\Tests\View\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\View\Component\Breakpoint;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridAlign;
use Redaxo\Core\View\Component\GridColumn;
use Redaxo\Core\View\Component\GridColumnRule;
use Redaxo\Core\View\Component\GridColumnStart;
use Redaxo\Core\View\Component\GridColumnWidth;
use Redaxo\Core\View\Component\GridGap;
use Redaxo\Core\View\Component\GridLayout;
use Redaxo\Core\View\Component\GridLayoutColumns;
use Redaxo\Core\View\Component\GridLayoutSplit;
use Redaxo\Core\View\Component\GridLayoutTiles;
use Redaxo\Core\View\Component\GridOrder;
use Redaxo\Core\View\Component\GridTileSize;
use Redaxo\Core\View\Component\GridVisibility;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function array_slice;

/** @internal */
final class GridTest extends TestCase
{
    public function testRendersTheColumnsWithTheirTokens(): void
    {
        $html = self::render(new Grid([
            new GridColumn('form', [
                new GridColumnRule(Breakpoint::Xl, width: 7),
                new GridColumnRule(Breakpoint::Md, width: 8),
            ]),
            new GridColumn('preview', [
                new GridColumnRule(Breakpoint::Xs, order: GridOrder::First),
                new GridColumnRule(Breakpoint::Md, width: 4, order: GridOrder::Source),
            ], alignY: GridAlign::Start),
            new GridColumn('hint', [
                new GridColumnRule(Breakpoint::Xs, visibility: GridVisibility::Hidden),
                new GridColumnRule(Breakpoint::Md, width: 8, start: 5, visibility: GridVisibility::Visible),
                new GridColumnRule(Breakpoint::Lg, start: GridColumnStart::Auto),
            ]),
        ], gap: GridGap::Large));

        self::assertSame(
            '<div class="rex-grid" data-gap="lg">'
            . '<div data-span="md:8 xl:7">form</div>'
            . '<div data-span="md:4" data-order="first md:source" data-align-y="start">preview</div>'
            . '<div data-span="md:8" data-start="md:5 lg:auto" data-visibility="hidden md:visible">hint</div>'
            . '</div>',
            $html,
        );
    }

    public function testPlainContentIsAColumnWithoutRules(): void
    {
        self::assertSame(
            '<div class="rex-grid"><div>&lt;b&gt;</div><div><b>bold</b></div></div>',
            self::render(new Grid(['<b>', Html::raw('<b>bold</b>')])),
        );
    }

    public function testEmptyGridRendersNothing(): void
    {
        self::assertSame('', self::render(new Grid([])));
    }

    public function testSingleColumnKeepsTheWrapper(): void
    {
        self::assertSame('<div class="rex-grid"><div>a</div></div>', self::render(new Grid(['a'])));
    }

    public function testLayouts(): void
    {
        self::assertSame('<div class="rex-grid" data-layout="split"><div>a</div></div>', self::render(new Grid(['a'], layout: new GridLayoutSplit())));
        self::assertSame('<div class="rex-grid" data-layout="split" data-from="md"><div>a</div></div>', self::render(new Grid(['a'], layout: new GridLayoutSplit(Breakpoint::Md))));
        self::assertSame('<div class="rex-grid" data-layout="tiles"><div>a</div></div>', self::render(new Grid(['a'], layout: new GridLayoutTiles())));
        self::assertSame('<div class="rex-grid" data-layout="tiles" data-tile-size="sm"><div>a</div></div>', self::render(new Grid(['a'], layout: new GridLayoutTiles(GridTileSize::Small))));
    }

    public function testGaps(): void
    {
        foreach ([GridGap::None, GridGap::Small, GridGap::Large] as $gap) {
            self::assertStringStartsWith('<div class="rex-grid" data-gap="' . $gap->value . '">', self::render(new Grid(['a'], gap: $gap)));
        }
        self::assertStringStartsWith('<div class="rex-grid">', self::render(new Grid(['a'])));
    }

    public function testSplitWidthsAndHorizontalAlignment(): void
    {
        $html = self::render(new Grid([
            'field',
            new GridColumn('buttons', [new GridColumnRule(Breakpoint::Xs, width: GridColumnWidth::Auto)], alignX: GridAlign::End),
            new GridColumn('more', [new GridColumnRule(Breakpoint::Md, width: 3)], alignX: GridAlign::Center),
        ], layout: new GridLayoutSplit()));

        self::assertStringContainsString('<div>field</div>', $html);
        self::assertStringContainsString('<div data-span="auto" data-align-x="end"><div>buttons</div></div>', $html);
        self::assertStringContainsString('<div data-span="md:3" data-align-x="center"><div>more</div></div>', $html);
    }

    public function testSubgridIsTheColumnItself(): void
    {
        $html = self::render(new Grid([
            new GridColumn(new Grid(['a', 'b'], subgrid: true, attributes: new HtmlAttributes(['id' => 'inner'])), [new GridColumnRule(Breakpoint::Md, width: 6)]),
            new Grid(['c'], subgrid: true),
        ]));

        self::assertSame(
            '<div class="rex-grid">'
            . '<div class="rex-grid" data-subgrid data-span="md:6" id="inner"><div>a</div><div>b</div></div>'
            . '<div class="rex-grid" data-subgrid><div>c</div></div>'
            . '</div>',
            $html,
        );
    }

    public function testAttributes(): void
    {
        $html = self::render(new Grid(
            [new GridColumn('a', attributes: new HtmlAttributes(['id' => 'col']))],
            attributes: new HtmlAttributes(['id' => 'grid', 'class' => ['extra']]),
        ));

        self::assertSame('<div class="rex-grid extra" id="grid"><div id="col">a</div></div>', $html);
    }

    /** @return iterable<string, array{GridLayout, GridColumnRule, bool}> */
    public static function provideAllowedProperties(): iterable
    {
        $layouts = ['columns' => new GridLayoutColumns(), 'split' => new GridLayoutSplit(), 'tiles' => new GridLayoutTiles()];
        $rules = [
            'width as number' => [new GridColumnRule(Breakpoint::Md, width: 4), ['columns' => true, 'split' => true, 'tiles' => false]],
            'width auto' => [new GridColumnRule(Breakpoint::Md, width: GridColumnWidth::Auto), ['columns' => false, 'split' => true, 'tiles' => false]],
            'width fill' => [new GridColumnRule(Breakpoint::Md, width: GridColumnWidth::Fill), ['columns' => false, 'split' => true, 'tiles' => false]],
            'start' => [new GridColumnRule(Breakpoint::Md, width: 4, start: 2), ['columns' => true, 'split' => false, 'tiles' => false]],
            'start auto' => [new GridColumnRule(Breakpoint::Md, start: GridColumnStart::Auto), ['columns' => true, 'split' => false, 'tiles' => false]],
            'order' => [new GridColumnRule(Breakpoint::Md, order: GridOrder::Last), ['columns' => true, 'split' => true, 'tiles' => true]],
            'visibility' => [new GridColumnRule(Breakpoint::Md, visibility: GridVisibility::Hidden), ['columns' => true, 'split' => true, 'tiles' => true]],
        ];

        foreach ($rules as $name => [$rule, $allowed]) {
            foreach ($layouts as $layoutName => $layout) {
                yield $name . ' in ' . $layoutName => [$layout, $rule, $allowed[$layoutName]];
            }
        }
    }

    #[DataProvider('provideAllowedProperties')]
    public function testAllowedProperties(GridLayout $layout, GridColumnRule $rule, bool $allowed): void
    {
        if (!$allowed) {
            $this->expectException(InvalidArgumentException::class);
        }

        $grid = new Grid([new GridColumn('a', [$rule])], layout: $layout);

        self::assertCount(1, $grid->columns);
    }

    public function testAlignmentIsAllowedInEveryLayout(): void
    {
        foreach ([new GridLayoutColumns(), new GridLayoutSplit(), new GridLayoutTiles()] as $layout) {
            $grid = new Grid([new GridColumn('a', alignX: GridAlign::End, alignY: GridAlign::Center)], layout: $layout);
            self::assertStringContainsString('data-align-x="end" data-align-y="center"', self::render($grid));
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     * @psalm-suppress InvalidArgument the invalid values are the test
     */
    public static function provideInvalid(): iterable
    {
        yield 'rule without values' => [static fn () => new GridColumnRule(Breakpoint::Md)];
        yield 'width 0' => [static fn () => new GridColumnRule(Breakpoint::Md, width: 0)]; // @phpstan-ignore argument.type
        yield 'width 13' => [static fn () => new GridColumnRule(Breakpoint::Md, width: GridLayoutColumns::COLUMNS + 1)]; // @phpstan-ignore argument.type
        yield 'start 13' => [static fn () => new GridColumnRule(Breakpoint::Md, start: 13)]; // @phpstan-ignore argument.type
        yield 'two rules for a breakpoint' => [static fn () => new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 4), new GridColumnRule(Breakpoint::Md, order: GridOrder::First)])];
        yield 'start with the default width' => [static fn () => new Grid([new GridColumn('a', [new GridColumnRule(Breakpoint::Md, start: 2)])])];
        yield 'start and width beyond 12' => [static fn () => new Grid([new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 6, start: 8)])])];
        yield 'inherited start and width beyond 12' => [static fn () => new Grid([new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 8), new GridColumnRule(Breakpoint::Lg, start: 6)])])];
        yield 'split width below from' => [static fn () => new Grid([new GridColumn('a', [new GridColumnRule(Breakpoint::Sm, width: 4)])], layout: new GridLayoutSplit(Breakpoint::Md))];
        yield 'split widths above 12' => [static fn () => new Grid([
            new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 8)]),
            new GridColumn('b', [new GridColumnRule(Breakpoint::Lg, width: 6)]),
        ], layout: new GridLayoutSplit(Breakpoint::Md))];
        yield 'split widths of 12 besides fill' => [static fn () => new Grid([
            new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 12)]),
            'b',
        ], layout: new GridLayoutSplit(Breakpoint::Md))];
    }

    /** @param callable(): mixed $create */
    #[DataProvider('provideInvalid')]
    public function testInvalid(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    public function testSplitIgnoresHiddenColumnsInTheSum(): void
    {
        $grid = new Grid([
            new GridColumn('a', [new GridColumnRule(Breakpoint::Md, width: 12)]),
            new GridColumn('b', [new GridColumnRule(Breakpoint::Xs, visibility: GridVisibility::Hidden)]),
        ], layout: new GridLayoutSplit(Breakpoint::Md));

        self::assertCount(2, $grid->columns);
    }

    public function testCssHasTheRulesOfAllBreakpointsAndColumns(): void
    {
        $css = (string) file_get_contents(Path::core('assets/css/src/components/grid.css'));

        preg_match_all('/@container rex-grid \(min-width: (\d+)px\)/', $css, $matches);
        $widths = array_map(static fn (Breakpoint $breakpoint): int => $breakpoint->minWidth(), array_slice(Breakpoint::cases(), 1));
        self::assertSame($widths, array_map(intval(...), $matches[1]));

        self::assertStringContainsString('repeat(' . GridLayoutColumns::COLUMNS . ', minmax(0, 1fr))', $css);
        foreach (Breakpoint::cases() as $breakpoint) {
            $prefix = Breakpoint::Xs === $breakpoint ? '' : $breakpoint->value . ':';
            for ($i = 1; $i <= GridLayoutColumns::COLUMNS; ++$i) {
                self::assertStringContainsString('[data-span~="' . $prefix . $i . '"]', $css);
                self::assertStringContainsString('[data-start~="' . $prefix . $i . '"]', $css);
            }
            self::assertStringNotContainsString('[data-span~="' . $prefix . (GridLayoutColumns::COLUMNS + 1) . '"]', $css);
            foreach ([GridColumnWidth::Auto, GridColumnWidth::Fill] as $width) {
                self::assertStringContainsString('[data-span~="' . $prefix . $width->value . '"]', $css);
            }
            foreach (GridOrder::cases() as $order) {
                self::assertStringContainsString('[data-order~="' . $prefix . $order->value . '"]', $css);
            }
            self::assertStringContainsString('[data-visibility~="' . $prefix . 'hidden"]', $css);
        }
    }

    private static function render(Grid $grid): string
    {
        return (string) preg_replace('/>\s+</', '><', trim((string) $grid->render()));
    }
}
