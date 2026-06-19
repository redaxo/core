<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\View\HtmlAttributes;

/**
 * Base for fields backed by a single form control (input, textarea, select) that accept additional,
 * purely presentational HTML attributes — e.g. `placeholder`, `min`/`max`/`step`, `pattern`, `data-*`.
 *
 * The attributes passed here form the lower-precedence base: each field merges its own structural
 * attributes (`name`, `id`, `type`, `value`, …) on top via {@see HtmlAttributes::with()}, so those
 * can not be overridden, while array attributes like `class` are extended rather than replaced.
 *
 * Attributes with a storage or validation consequence (column width, `required`, …) stay first-class
 * constructor arguments — only loose presentation belongs in this bag.
 */
abstract class AbstractInputField extends MetaField
{
    public function __construct(
        string $name,
        string $label,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
        protected readonly HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct($name, $label, $note, $required, $default);
    }
}
