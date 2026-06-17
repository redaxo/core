<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Content\Template;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\Validator\Validator;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * Base class for all meta field types.
 *
 * The class you instantiate *is* the field type — there is no separate type registry. Static
 * configuration goes into the constructor; dynamic behaviour is expressed by overriding methods
 * (typically via an anonymous subclass for one-off fields, or a named subclass for a reusable type,
 * e.g. shipped by an addon).
 */
abstract class MetaField
{
    public function __construct(
        /** Field name without the entity prefix. */
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $note = null,
        public readonly bool $required = false,
        public readonly ?string $default = null,
    ) {}

    /** Full column name including the entity prefix. */
    final public function columnName(MetaEntity $entity): string
    {
        return $entity->prefix() . $this->name;
    }

    /**
     * The database column backing this field, or `null` if the field stores no value
     * (e.g. a {@see LegendField}). Used by the migrate column sync.
     */
    abstract public function column(MetaEntity $entity): ?Column;

    /** Renders the form control only (without label/note wrapper). */
    abstract public function renderInput(MetaContext $ctx): string;

    /**
     * Renders the complete form group (label, control, note).
     *
     * The default wraps {@see self::renderInput()}; override for fully custom markup
     * (a {@see LegendField} for example renders no label wrapper at all).
     */
    public function render(MetaContext $ctx): string
    {
        $note = null !== $this->note
            ? sprintf('<p class="help-block rex-note">%s</p>', escape($this->note))
            : '';

        return sprintf(
            '<div class="form-group"><label for="%s">%s</label>%s%s</div>',
            escape($this->columnName($ctx->entity)),
            escape($this->label),
            $this->renderInput($ctx),
            $note,
        );
    }

    /** Reads and normalises the submitted value into its DB representation. */
    public function parseRequest(MetaContext $ctx): int|string|null
    {
        return Request::post($this->columnName($ctx->entity), 'string', '');
    }

    /** Converts the stored DB value into the value exposed to the application (e.g. `getValue()`). */
    public function format(mixed $stored): mixed
    {
        return $stored;
    }

    /**
     * Per-field validation (#367). Return a configured validator, or `null` to skip.
     *
     * Override to add rules, e.g. `return Validator::factory()->add('maxLength', null, 255);`.
     */
    public function validator(): ?Validator
    {
        return null;
    }

    /**
     * Whether this field is visible/applicable in the current context (#231).
     *
     * The default allows it everywhere; override to restrict (analogous to
     * {@see Template::isAllowedInCategory()}).
     */
    public function isAllowed(MetaContext $ctx): bool
    {
        return true;
    }
}
