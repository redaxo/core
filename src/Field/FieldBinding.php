<?php

namespace Redaxo\Core\Field;

/**
 * The concrete name, value and error a {@see Field} is bound to for one render pass.
 *
 * A field is a reusable, value-less definition; this struct carries the per-render state: the
 * resolved HTML name (which doubles as the id base, prefix already applied), the current value in the
 * field's own representation, and an optional error to surface in the group. It is the single
 * extension point through which a form or the meta-info layer feeds a field its data — fields never
 * read ambient state themselves.
 */
final readonly class FieldBinding
{
    public function __construct(
        public string $name,
        public mixed $value = null,
        public ?string $error = null,
    ) {}
}
