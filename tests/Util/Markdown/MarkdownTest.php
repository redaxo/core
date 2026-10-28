<?php

namespace Redaxo\Core\Tests\Util\Markdown;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Util\Markdown\Markdown;
use Redaxo\Core\Util\Str;

/** @internal */
final class MarkdownTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function testParse(string $expected, string $code): void
    {
        self::assertSame($expected, Markdown::factory()->parse($code));
    }

    /** @return list<array{string, string}> */
    public static function parseProvider(): array
    {
        return [
            ['', ''],
            ['<p>foo <em>bar</em> <strong>baz</strong></p>', 'foo _bar_ **baz**'],
            ["<p>foo<br />\nbar</p>\n<p>baz</p>", "foo\nbar\n\nbaz"],
            [
                <<<'HTML'
                    <pre><code class="language-php">    &lt;script&gt;foo()&lt;/script&gt;
                    </code></pre>
                    HTML,
                <<<'MD'
                    <script> foo() </script>

                    ```php
                        <script>foo()</script>
                    ```
                    MD,
            ],
            [
                <<<'HTML'
                    <pre><code class="language-html">&lt;button onclick&#61;&#34;showBox();&#34;&gt;Foo&lt;/button&gt;
                    </code></pre>
                    <p>Events: <code>box-onshow</code>, box-onclose</p>
                    HTML,
                <<<'MD'
                    ```html
                    <button onclick="showBox();">Foo</button>
                    ```

                    Events: `box-onshow`, box-onclose
                    MD,
            ],
            [
                <<<'HTML'
                    <table>
                    <thead>
                    <tr>
                    <th class="rex-u-align-end">Foo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                    <td class="rex-u-align-end">Bar</td>
                    </tr>
                    </tbody>
                    </table>
                    HTML,
                "| Foo |\n| --: |\n| Bar |",
            ],
        ];
    }

    public function testParseWithSanitizerConfig(): void
    {
        $code = '<form><input name="foo"></form>';

        self::assertSame('', Markdown::factory()->parse($code));

        $config = Str::htmlSanitizerConfig()->allowElement('form')->allowElement('input', ['name']);
        self::assertSame('<form><input name="foo" /></form>', Markdown::factory()->parse($code, sanitizerConfig: $config));
    }

    public function testParseWithToc(): void
    {
        $input = <<<'MARKDOWN'
            Test
            ====

            Foo bar

            Sub 1
            -----

            Foo bar

            ### Sub 1.1

            ### Sub 1.2

            #### Sub Sub 1.2.1

            ##### Sub Sub 1.2.1.1

            ## Sub 2

            ### Sub 2.1

            ## Sub 3

            ### Sub 3.1

            ## Duplicate Test

            ## Duplicate Test

            ## [Title with Markdown](#sub-1)

            ## <i>Title with HTML</i>

            ## Title with "quotes" & 'other' special &lt;chars&gt;

            ## Title with explicit id {#explicit}
            MARKDOWN;

        [$toc, $content] = Markdown::factory()->parseWithToc($input, 2, 4);

        $expected = <<<'HTML'
            <ul>
            <li>
            <a href="#sub-1">Sub 1</a>
            <ul>
            <li>
            <a href="#sub-1-1">Sub 1.1</a>
            </li>
            <li>
            <a href="#sub-1-2">Sub 1.2</a>
            <ul>
            <li>
            <a href="#sub-sub-1-2-1">Sub Sub 1.2.1</a>
            </li>
            </ul>
            </li>
            </ul>
            <li>
            <a href="#sub-2">Sub 2</a>
            <ul>
            <li>
            <a href="#sub-2-1">Sub 2.1</a>
            </li>
            </ul>
            <li>
            <a href="#sub-3">Sub 3</a>
            <ul>
            <li>
            <a href="#sub-3-1">Sub 3.1</a>
            </li>
            </ul>
            <li>
            <a href="#duplicate-test">Duplicate Test</a>
            </li>
            <li>
            <a href="#duplicate-test-1">Duplicate Test</a>
            </li>
            <li>
            <a href="#title-with-markdown">Title with Markdown</a>
            </li>
            <li>
            <a href="#title-with-html">Title with HTML</a>
            </li>
            <li>
            <a href="#title-with-quotes-other-special-chars">Title with &quot;quotes&quot; &amp; &#039;other&#039; special &lt;chars&gt;</a>
            </li>
            <li>
            <a href="#explicit">Title with explicit id</a>
            </li>
            </ul>

            HTML;

        self::assertSame($expected, $toc);

        self::assertStringContainsString('<h2 id="duplicate-test">Duplicate Test</h2>', $content);
        self::assertStringContainsString('<h2 id="duplicate-test-1">Duplicate Test</h2>', $content);
        self::assertStringContainsString('<h2 id="explicit">Title with explicit id</h2>', $content);
    }

    public function testParseWithHighlightPhp(): void
    {
        $code = "```php\necho 'foo';\n```";

        $expected = '<pre><code><span class="rex-code-default"></span><span class="rex-code-keyword">echo </span><span class="rex-code-string">&#039;foo&#039;</span><span class="rex-code-keyword">;</span></code></pre>';

        self::assertSame($expected, Markdown::factory()->parse($code, highlightPhp: true));
    }

    public function testParseWithoutSoftLineBreaks(): void
    {
        self::assertSame("<p>foo\nbar</p>", Markdown::factory()->parse("foo\nbar", softLineBreaks: false));
    }
}
