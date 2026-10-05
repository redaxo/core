<?php

namespace Redaxo\Core\Util;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\RuntimeException;
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
        $parser = $this->createParser($softLineBreaks, $highlightPhp);

        return Str::sanitizeHtml($parser->text($code), $sanitizerConfig);
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
        $parser = $this->createParser($softLineBreaks, $highlightPhp);
        $parser->generateToc = true;
        $parser->topLevel = $topLevel;
        $parser->bottomLevel = $bottomLevel;

        $content = Str::sanitizeHtml($parser->text($code), $sanitizerConfig);
        $headers = $parser->headers;

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

    private function createParser(bool $softLineBreaks, bool $highlightPhp): Parsedown
    {
        $parser = new Parsedown();
        $parser->setBreaksEnabled($softLineBreaks);
        $parser->highlightPhp = $highlightPhp;

        return $parser;
    }
}
