<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * Structural grouping element: opens a `<fieldset>` (with an optional `<legend>`) that wraps all following
 * fields, up to the next {@see Fieldset} or the end of the form. Stores no value (no column).
 *
 * The `$attributes` are applied to the `<fieldset>` element; the legend needs none.
 */
final class Fieldset extends MetaField
{
    public function __construct(
        public readonly ?string $legend = null,
        private readonly HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct('', $legend ?? '');
    }

    public function column(MetaEntity $entity): ?Column
    {
        return null;
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        return null;
    }

    public function renderInput(MetaContext $context): string
    {
        return null === $this->legend ? '' : sprintf('<legend>%s</legend>', escape($this->legend));
    }

    /**
     * Renders the *opening* `<fieldset>` tag plus the optional legend. The closing tag is emitted by the
     * handler once the group's fields have been rendered, so this intentionally returns an unbalanced fragment.
     */
    public function render(MetaContext $context): string
    {
        $attributes = $this->attributes->toString();

        return sprintf('<fieldset%s>%s', '' === $attributes ? '' : ' ' . $attributes, $this->renderInput($context));
    }
}
