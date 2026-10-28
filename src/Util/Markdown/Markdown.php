<?php

namespace Redaxo\Core\Util\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Util\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

use function Redaxo\Core\View\escape;

/**
 * Markdown parser.
 */
class Markdown
{
    use FactoryTrait;

    final private function __construct() {}

    public static function factory(): static
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    /**
     * Parses markdown code.
     *
     * @param string $code Markdown code
     * @param HtmlSanitizerConfig|null $sanitizerConfig Allowlist for the resulting html, see {@see Str::sanitizeHtml()}
     *
     * @return string HTML code
     */
    public function parse(
        string $code,
        bool $softLineBreaks = true,
        bool $highlightPhp = false,
        ?HtmlSanitizerConfig $sanitizerConfig = null,
    ): string {
        $html = $this->createEnvironment($softLineBreaks, $highlightPhp)->convert($code)->getContent();

        return trim(Str::sanitizeHtml($html, $sanitizerConfig));
    }

    /**
     * Parses markdown code and extracts a table-of-content.
     *
     * @param string $code Markdown code
     * @param int $topLevel Top included headline level for TOC, e.g. `1` for `<h1>`
     * @param int $bottomLevel Bottom included headline level for TOC, e.g. `6` for `<h6>`
     * @param HtmlSanitizerConfig|null $sanitizerConfig Allowlist for the resulting html, see {@see Str::sanitizeHtml()}
     *
     * @return list{string, string} tupel of table-of-content and content
     */
    public function parseWithToc(
        string $code,
        int $topLevel = 2,
        int $bottomLevel = 3,
        bool $softLineBreaks = true,
        bool $highlightPhp = false,
        ?HtmlSanitizerConfig $sanitizerConfig = null,
    ): array {
        $headingIds = new HeadingIdListener($topLevel, $bottomLevel);
        $html = $this->createEnvironment($softLineBreaks, $highlightPhp, $headingIds)->convert($code)->getContent();

        $content = trim(Str::sanitizeHtml($html, $sanitizerConfig));
        $headers = $headingIds->headings;

        $previous = $topLevel - 1;
        $toc = '';

        foreach ($headers as $header) {
            $level = $header['level'];

            if ($level > $previous) {
                if ($level > $previous + 1) {
                    $message = 'The headline structure in the given markdown document is malformed, ';
                    if ($previous < $topLevel) {
                        $message .= "it starts with a h$level instead of a h$topLevel.";
                    } else {
                        $message .= "a h$previous is followed by a h$level, but only a h" . ($previous + 1) . ' or lower is allowed.';
                    }

                    throw new RuntimeException($message);
                }

                $toc .= "<ul>\n";
                $previous = $level;
            } elseif ($level < $previous) {
                for (; $level < $previous; --$previous) {
                    $toc .= "</li>\n";
                    $toc .= "</ul>\n";
                }
            } else {
                $toc .= "</li>\n";
            }

            $toc .= "<li>\n";
            $toc .= '<a href="#' . escape($header['id']) . '">' . escape($header['text']) . "</a>\n";
        }

        for (; $previous > $topLevel - 1; --$previous) {
            $toc .= "</li>\n";
            $toc .= "</ul>\n";
        }

        return [$toc, $content];
    }

    private function createEnvironment(
        bool $softLineBreaks,
        bool $highlightPhp,
        ?HeadingIdListener $headingIds = null,
    ): MarkdownConverter {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'renderer' => [
                'soft_break' => $softLineBreaks ? "<br>\n" : "\n",
            ],
            'table' => [
                'alignment_attributes' => [
                    'left' => ['class' => 'rex-u-align-start'],
                    'center' => ['class' => 'rex-u-align-center'],
                    'right' => ['class' => 'rex-u-align-end'],
                ],
            ],
        ]);

        $environment
            ->addExtension(new CommonMarkCoreExtension())
            ->addExtension(new AutolinkExtension())
            ->addExtension(new StrikethroughExtension())
            ->addExtension(new TableExtension())
            ->addExtension(new FootnoteExtension())
            ->addExtension(new DescriptionListExtension())
            ->addExtension(new AttributesExtension());

        if ($headingIds) {
            // runs after the attributes listener, so that explicit ids (`{#foo}`) are respected
            $environment->addEventListener(DocumentParsedEvent::class, $headingIds, -10);
        }

        if ($highlightPhp) {
            $environment->addRenderer(FencedCode::class, new PhpHighlightRenderer(), 10);
        }

        return new MarkdownConverter($environment);
    }
}
