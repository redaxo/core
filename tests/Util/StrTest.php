<?php

namespace Redaxo\Core\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Util\Str;

/**
 * @internal
 * @psalm-import-type TUrlParams from Str
 */
final class StrTest extends TestCase
{
    public function testSize(): void
    {
        self::assertEquals(3, Str::size('aä'));
    }

    /** @return list<array{0: string, 1: string, 2?: string, 3?: string}> */
    public static function normalizeProvider(): array
    {
        return [
            [
                'ae_oe_ue_ae_oe_ue_ss_ae_oe_ue_ae_oe_ue',
                "Ä Ö Ü ä ö ü ß A\xcc\x88 O\xcc\x88 U\xcc\x88 a\xcc\x88 o\xcc\x88 u\xcc\x88",
            ],
            ['test-12-3-4-a', 'Test. 12+3+-4 [a]', '-'],
            ['test123', '"test" 123', ''],
            ['[€_1]', '[€ 1]', '_', '[]€'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $expected, string $string, string $replaceChar = '_', string $allowedChars = ''): void
    {
        self::assertEquals($expected, Str::normalize($string, $replaceChar, $allowedChars));
    }

    /** @return list<array{string, array<int|string, string|int>}> */
    public static function splitProvider(): array
    {
        return [
            ['',                                          []],
            ['a b c',                                     ['a', 'b', 'c']],
            ['"a b" cdef \'ghi kl\'',                     ['a b', 'cdef', 'ghi kl']],
            ['a=1 b=xyz c="hu hu" 123=\'he he\'',         ['a' => '1', 'b' => 'xyz', 'c' => 'hu hu', '123' => 'he he']],
            ['a="a \"b\" c" b=\'a \\\'b\\\'\' c="a\\\\"', ['a' => 'a "b" c', 'b' => "a 'b'", 'c' => 'a\\']],
            ["\n a=1\n b='aa\nbb'\n c='a'\n ",            ['a' => '1', 'b' => "aa\nbb", 'c' => 'a']],
            ['"a b" c "d e',                              ['a b', 'c', '"d', 'e']],
            ['"a"b" "c"d',                                ['a"b', '"c"d']],
        ];
    }

    /** @param array<int|string, string|int> $expectedArray */
    #[DataProvider('splitProvider')]
    public function testSplit(string $string, array $expectedArray): void
    {
        self::assertSame($expectedArray, Str::split($string));
    }

    /** @return list<array{0: string, 1: TUrlParams}> */
    public static function buildQueryProvider(): array
    {
        return [
            ['', []],
            ['page=system/settings&a%2Bb=test+test', ['page' => 'system/settings', 'a+b' => 'test test']],
            ['arr[0]=a&arr[1]=b&arr[key]=c', ['arr' => ['a', 'b', 'key' => 'c']]],
        ];
    }

    /** @param TUrlParams $params */
    #[DataProvider('buildQueryProvider')]
    public function testBuildQuery(string $expected, array $params): void
    {
        self::assertEquals($expected, Str::buildQuery($params));
    }

    public function testBuildAttributes(): void
    {
        self::assertEquals(
            ' id="rex-test" class="a b" alt="" checked data-foo="&lt;foo&gt; &amp; &quot;bar&quot;" href="index.php?foo=1&amp;bar=2"',
            Str::buildAttributes([
                'id' => 'rex-test',
                'class' => ['a', 'b'],
                'alt' => '',
                'checked',
                'data-foo' => '<foo> & "bar"',
                'href' => 'index.php?foo=1&amp;bar=2',
            ]),
        );
    }

    #[DataProvider('provideSanitizeHtml')]
    public function testSanitizeHtml(string $expected, string $input): void
    {
        self::assertSame($expected, Str::sanitizeHtml($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideSanitizeHtml(): iterable
    {
        yield 'safe markup' => [
            '<p align="center" class="lead"><img src="foo.jpg" style="width: 200px" alt="Foo" /></p><a name="test" id="test"></a>',
            '<p align=center class="lead"><img src="foo.jpg" style="width: 200px" alt="Foo"></p><a name="test" id="test"></a>',
        ];
        yield 'external urls' => [
            '<a href="https://example.org/foo">Foo</a><img src="https://example.org/foo.png" /><img src="//example.org/bar.png" />',
            '<a href="https://example.org/foo">Foo</a><img src="https://example.org/foo.png"><img src="//example.org/bar.png">',
        ];
        yield 'script element' => [
            '<p>Foo</p>',
            '<p>Foo</p><script>alert(1);</script>',
        ];
        yield 'event handler' => [
            '<a href="index.php">Foo</a><img src="foo.jpg" />',
            '<a href="index.php" onclick="alert(1)">Foo</a><img src="foo.jpg" onmouseover="alert(1)">',
        ];
        yield 'javascript url' => [
            '<a>Foo</a><a>Bar</a>',
            '<a href="javascript:alert(1)">Foo</a><a href="jav&#x09;ascript:alert(1)">Bar</a>',
        ];
        yield 'unsafe elements' => [
            '<p>Foo</p>',
            '<iframe src="https://example.org"></iframe><form action="/"><input name="foo"></form><p>Foo</p>',
        ];
        yield 'code with event handler' => [
            '<pre><code>&lt;button onclick&#61;&#34;window.location.replace(url)&#34;&gt;Foo&lt;/button&gt;</code></pre>',
            '<pre><code>&lt;button onclick="window.location.replace(url)"&gt;Foo&lt;/button&gt;</code></pre>',
        ];
        yield 'text resembling event handlers' => [
            '<p>The events <code>consent-onshow</code> and consent-onclose are triggered via document.location.</p>',
            '<p>The events <code>consent-onshow</code> and consent-onclose are triggered via document.location.</p>',
        ];
    }
}
