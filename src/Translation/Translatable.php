<?php

namespace Redaxo\Core\Translation;

use Override;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Renderable;

use function array_values;

/**
 * A translation key that is resolved lazily, when the text is actually needed.
 *
 * Unlike the result of {@see I18n::msg()} it can be created in constant expressions, so translated texts can be
 * passed to attributes (`#[Text(new Translatable('my_label'))]`).
 *
 * In HTML it renders like {@see I18n::msg()}, keeping basic tags of the message (`<b>`, `<code>` …); outside of HTML,
 * {@see self::translate()} returns the plain text. It is deliberately not `Stringable`, so that the context has to be
 * chosen explicitly.
 */
final readonly class Translatable implements Renderable
{
    /** @var list<string|int> */
    public array $replacements;

    public function __construct(
        public string $key,
        string|int ...$replacements,
    ) {
        $this->replacements = array_values($replacements);
    }

    /**
     * @internal
     * @param array{key: string, replacements: list<string|int>} $data
     */
    public static function __set_state(array $data): self
    {
        return new self($data['key'], ...$data['replacements']);
    }

    /** Returns the plain, not escaped text, e.g. for attribute values. */
    public function translate(?string $locale = null): string
    {
        return null === $locale
            ? I18n::rawMsg($this->key, ...$this->replacements)
            : I18n::rawMsgInLocale($this->key, $locale, ...$this->replacements);
    }

    #[Override]
    public function render(): Html
    {
        return Html::raw(I18n::msg($this->key, ...$this->replacements));
    }

    /** Returns the plain text of a text that is either given directly or as translation key. */
    public static function text(string|self $text): string
    {
        return $text instanceof self ? $text->translate() : $text;
    }
}
