<?php

namespace Redaxo\Core\Util\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Override;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

/**
 * Renders fenced `php` code blocks via `highlight_string()`, other blocks are left to the default renderer.
 *
 * @internal
 */
final class PhpHighlightRenderer implements NodeRendererInterface
{
    #[Override]
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        $node = Type::instanceOf($node, FencedCode::class);

        if ('php' !== ($node->getInfoWords()[0] ?? null)) {
            return null;
        }

        $text = $node->getLiteral();
        if (str_ends_with($text, "\n")) {
            $text = substr($text, 0, -1);
        }

        $missingPhpStart = !str_contains($text, '<?php') && !str_contains($text, '<?=');
        if ($missingPhpStart) {
            $text = '<?php ' . $text;
        }

        $text = highlight_string($text, true);
        if (str_starts_with($text, '<pre>')) {
            $text = substr($text, 5, -6);
        }

        // normalize the wrapper markup that PHP 8.3+ emits around the highlighted code
        $text = Type::string(preg_replace('@<code style="color:[^"]+">@', '<code>', $text, 1));
        $text = Type::string(preg_replace('@<span style="color:[^"]+">\n(<span style="color:[^"]+">)@', '$1', $text, 1));
        $text = Type::string(preg_replace('@<\/span>\n(<\/span>\n<\/code>)$@', '$1', $text, 1));

        if ($missingPhpStart) {
            $text = Type::string(preg_replace('@(<span style="color:[^"]+">)(?:&lt;|<)\?php(?:&nbsp;|\s)@', '$1', $text, 1));
        }

        return '<pre>' . Str::highlightClasses($text) . '</pre>';
    }
}
