<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Http\Request;
use Redaxo\Core\Validator\Validator;
use Redaxo\Core\View\HasView;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\ViewResolver;

/**
 * Base class for all form field components.
 *
 * A field is an immutable, declarative description of one form control. The class you instantiate
 * *is* the field type — there is no type registry: static configuration goes into the constructor,
 * dynamic behaviour is expressed by overriding methods (typically an anonymous subclass for a one-off
 * field, or a named subclass for a reusable type shipped by an addon). This mirrors the meta-info
 * field model it generalizes, but is rendered through the view system and decoupled from any entity.
 *
 * A field is {@see Renderable}: {@see self::render()} produces the full form group (label, control,
 * note, error) and {@see self::renderInput()} the bare control. Both render through a co-located
 * `*.view.php`, so the markup is overridable per class via {@see ViewResolver}.
 *
 * The field carries no value of its own — it is bound to a concrete name/value/error via a
 * {@see FieldBinding} just before rendering ({@see self::boundTo()}). Standalone the constructor seeds
 * the binding with the field's default; a form or the meta-info layer rebinds it to the value read
 * from its storage. This keeps the field a reusable definition while staying a first-class
 * {@see Renderable}.
 */
abstract class Field implements Renderable
{
    use HasView;

    /** The concrete name/value/error this field is bound to for the current render pass. */
    public private(set) FieldBinding $binding;

    public function __construct(
        public readonly string $name,
        public readonly string $label = '',
        public readonly ?string $note = null,
        public readonly bool $required = false,
        public readonly ?string $default = null,
        /** Presentational base attributes (placeholder, data-*, …); the view merges its structural ones on top. */
        public readonly HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        $this->binding = new FieldBinding($name, $default);
    }

    /**
     * Returns a clone bound to the given name/value/error.
     *
     * The clone is shallow, which is safe because every field property is readonly and immutable
     * ({@see HtmlAttributes} included). Subclasses must not introduce mutable state.
     */
    final public function boundTo(FieldBinding $binding): static
    {
        $clone = clone $this;
        $clone->binding = $binding;

        return $clone;
    }

    /** Convenience for standalone use: bind a value while keeping the field's own name. */
    final public function withValue(mixed $value): static
    {
        return $this->boundTo(new FieldBinding($this->name, $value));
    }

    /** Reads and normalises the submitted value (for the given, already-resolved name) into its storage representation. */
    public function parseRequest(string $name): mixed
    {
        return Request::post($name, 'string', '');
    }

    /** Converts the stored value into the value exposed to the application. */
    public function format(mixed $stored): mixed
    {
        return $stored;
    }

    /** Per-field validation; return a configured validator, or `null` to skip. */
    public function validator(): ?Validator
    {
        return null;
    }

    /** Renders the bare form control (without label/note/error wrapper). */
    public function renderInput(): Html
    {
        return Html::raw($this->renderView($this));
    }

    /** Renders the full form group (label, control, note, error). */
    public function render(): Html
    {
        return new FieldGroup($this)->render();
    }
}
