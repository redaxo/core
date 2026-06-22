<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;

/**
 * Implemented by field types whose value maps to a database column.
 *
 * Only schema-syncing consumers (the meta-info column sync) query this — plain database or config
 * forms operate on existing storage and ignore it. The column *shape* is intrinsic to the field type
 * (e.g. `varchar(maxLength)`), while the name (and thus any entity prefix) is supplied by the caller.
 */
interface ProvidesColumn
{
    public function column(string $name): Column;
}
