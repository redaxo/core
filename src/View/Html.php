<?php

namespace Redaxo\Core\View;

use Closure;
use Stringable;

/**
 * A string that is known to be safe HTML and is therefore emitted without further escaping.
 *
 * The typed currency of the view system: instead of a runtime "should this be escaped?" flag, the
 * distinction lives in the type. A plain `string` is untrusted; an {@see Html} instance is trusted.
 *
 * - {@see Html::from()} normalizes a `string|Renderable` content value (escapes plain text, renders
 *   a {@see Renderable}) — the typical choice for a content property at the output site.
 * - {@see Html::raw()} wraps already-safe markup unchanged — use only when you can vouch for it.
 * - {@see Html::capture()} captures the output of a closure, so callers can write real inline HTML
 *   (leaving PHP mode inside the closure) instead of building markup in a string literal.
 *
 * {@see Html} is itself {@see Renderable} (it renders to itself), so it satisfies any property that
 * accepts a renderable.
 */
final readonly class Html implements Renderable, Stringable
{
    private function __construct(
        private string $html,
    ) {}

    /**
     * Wraps already-safe HTML markup unchanged.
     *
     * The caller vouches that `$html` is safe to emit. Passing untrusted input here is the
     * deliberate escaping bypass — handle with care.
     */
    public static function raw(string $html): self
    {
        return new self($html);
    }

    /**
     * Normalizes a content value to safe HTML: a plain `string` is escaped, a {@see Renderable}
     * (a component, or {@see Html} itself) is rendered.
     */
    public static function from(string|Renderable $value): self
    {
        return $value instanceof Renderable ? $value->render() : new self(escape($value));
    }

    /**
     * Captures the output a closure emits and wraps it as safe HTML.
     *
     * Lets callers write inline HTML at a call site (leaving PHP mode inside the closure) rather
     * than assembling markup inside a string. The closure is responsible for escaping its own
     * dynamic values, exactly like a view file.
     */
    public static function capture(Closure $closure): self
    {
        return new self(Renderer::capture($closure));
    }

    public function render(): self
    {
        return $this;
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
