<?php

namespace Redaxo\Core\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Util\Markdown;

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

                    <pre><code class="language-php">    &lt;script&gt;foo()&lt;/script&gt;</code></pre>
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
                    <pre><code class="language-html">&lt;button onclick&#61;&#34;showBox();&#34;&gt;Foo&lt;/button&gt;</code></pre>
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
                    <th style="text-align: right;">Foo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                    <td style="text-align: right;">Bar</td>
                    </tr>
                    </tbody>
                    </table>
                    HTML,
                "| Foo |\n| --: |\n| Bar |",
            ],
        ];
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
            MARKDOWN;

        [$toc] = Markdown::factory()->parseWithToc($input, 2, 4);

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
            </ul>

            HTML;

        self::assertSame($expected, $toc);
    }
}
