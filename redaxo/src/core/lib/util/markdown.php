<?php

/**
 * Markdown parser.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_markdown
{
    use rex_factory_trait;

    public const SOFT_LINE_BREAKS = 'soft_line_breaks';
    public const HIGHLIGHT_PHP = 'highlight_php';

    private function __construct() {}

    /**
     * @return static
     */
    public static function factory()
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    /**
     * Parses markdown code.
     *
     * @param string $code Markdown code
     * @param array<self::*, bool>|bool $options
     *
     * @return string HTML code
     */
    public function parse($code, $options = [])
    {
        // deprecated bool param
        $options = is_bool($options) ? [self::SOFT_LINE_BREAKS => $options] : $options;

        $parser = new rex_parsedown();
        $parser->setBreaksEnabled($options[self::SOFT_LINE_BREAKS] ?? true);
        $parser->highlightPhp = $options[self::HIGHLIGHT_PHP] ?? false;

        return rex_string::sanitizeHtml($parser->text($code));
    }

    /**
     * Parses markdown code and extracts a table-of-content.
     *
     * @param string $code Markdown code
     * @param int $topLevel Top included headline level for TOC, e.g. `1` for `<h1>`
     * @param int $bottomLevel Bottom included headline level for TOC, e.g. `6` for `<h6>`
     * @param array<self::*, bool>|bool $options
     *
     * @return list{string, string} tupel of table-of-content and content
     */
    public function parseWithToc($code, $topLevel = 2, $bottomLevel = 3, $options = [])
    {
        // deprecated bool param
        $options = is_bool($options) ? [self::SOFT_LINE_BREAKS => $options] : $options;

        $parser = new rex_parsedown();
        $parser->setBreaksEnabled($options[self::SOFT_LINE_BREAKS] ?? true);
        $parser->highlightPhp = $options[self::HIGHLIGHT_PHP] ?? false;

        $parser->generateToc = true;
        $parser->topLevel = $topLevel;
        $parser->bottomLevel = $bottomLevel;

        $content = rex_string::sanitizeHtml($parser->text($code));
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

                    throw new rex_exception($message);
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
            $toc .= '<a href="#' . rex_escape($header['id']) . '">' . rex_escape($header['text']) . "</a>\n";
        }

        for (; $previous > $topLevel - 1; --$previous) {
            $toc .= "</li>\n";
            $toc .= "</ul>\n";
        }

        return [$toc, $content];
    }
}

/**
 * @internal
 */
final class rex_parsedown extends ParsedownExtra
{
    /** @var bool */
    public $highlightPhp = false;

    /** @var bool */
    public $generateToc = false;
    /** @var int */
    public $topLevel = 2;
    /** @var int */
    public $bottomLevel = 3;
    /** @var list<array{level: int, id: string, text: string}> */
    public $headers = [];

    /** @var array<string, true> */
    private $ids = [];

    /**
     * @return string
     */
    public function text($text)
    {
        // https://github.com/erusev/parsedown-extra/issues/173
        $errorReporting = error_reporting(error_reporting() & ~E_DEPRECATED);

        try {
            return parent::text($text);
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * @return array|null
     */
    protected function blockHeader($Line)
    {
        $block = parent::blockHeader($Line);

        return $this->handleHeader($block);
    }

    /**
     * @return array|null
     */
    protected function blockSetextHeader($Line, array $Block = [])
    {
        $block = parent::blockSetextHeader($Line, $Block);

        return $this->handleHeader($block);
    }

    /**
     * @return array
     */
    protected function blockFencedCodeComplete($Block)
    {
        /** @var array $Block */
        $Block = parent::blockFencedCodeComplete($Block);

        if (!$this->highlightPhp) {
            return $Block;
        }

        /** @psalm-suppress MixedArrayAccess */
        if ('language-php' !== ($Block['element']['text']['attributes']['class'] ?? null)) {
            return $Block;
        }

        /**
         * @var string $text
         * @psalm-suppress MixedArrayAccess
         */
        $text = $Block['element']['text']['text'];

        $missingPhpStart = !str_contains($text, '<?php') && !str_contains($text, '<?=');
        if ($missingPhpStart) {
            $text = '<?php ' . $text;
        }

        $text = highlight_string($text, true);
        if (str_starts_with($text, '<pre>')) {
            $text = substr($text, 5, -6);
        }

        // php 8.3 fix
        $text = preg_replace('@<code style="color:[^"]+">@', '<code>', $text, 1);
        $text = preg_replace('@<span style="color:[^"]+">\n(<span style="color:[^"]+">)@', '$1', $text, 1);
        $text = preg_replace('@<\/span>\n(<\/span>\n<\/code>)$@', '$1', $text, 1);

        if ($missingPhpStart) {
            $text = preg_replace('@(<span style="color:[^"]+">)(?:&lt;|<)\?php(?:&nbsp;|\s)@', '$1', $text, 1);
        }

        /** @psalm-suppress MixedArrayAssignment */
        $Block['element']['rawHtml'] = $text;
        /** @psalm-suppress MixedArrayAccess */
        unset($Block['element']['text'], $Block['element']['handler']);

        return $Block;
    }

    /**
     * @return array|null
     */
    private function handleHeader(?array $block = null)
    {
        if (!$this->generateToc) {
            return $block;
        }

        if (!$block) {
            return $block;
        }

        [$level] = sscanf($block['element']['name'], 'h%d');

        $plainText = strip_tags($this->{$block['element']['handler']}($block['element']['text']));
        $plainText = htmlspecialchars_decode($plainText);

        if (!isset($block['element']['attributes']['id'])) {
            $baseId = $id = rex_string::normalize($plainText, '-');

            for ($i = 1; isset($this->ids[$id]); ++$i) {
                $id = $baseId . '-' . $i;
            }

            $block['element']['attributes']['id'] = $id;
        }

        $id = $block['element']['attributes']['id'];
        $this->ids[$id] = true;

        if ($level >= $this->topLevel && $level <= $this->bottomLevel) {
            $this->headers[] = [
                'level' => $level,
                'id' => $id,
                'text' => $plainText,
            ];
        }

        return $block;
    }
}
