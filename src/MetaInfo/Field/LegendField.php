<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function Redaxo\Core\View\escape;
use function sprintf;

/** Visual grouping element. Renders a legend and stores no value (no column). */
final class LegendField extends MetaField
{
    public function __construct(string $name, string $label)
    {
        parent::__construct($name, $label);
    }

    public function column(MetaEntity $entity): ?Column
    {
        return null;
    }

    public function parseRequest(MetaContext $ctx): int|string|null
    {
        return null;
    }

    public function renderInput(MetaContext $ctx): string
    {
        return sprintf('<legend>%s</legend>', escape($this->label));
    }

    public function render(MetaContext $ctx): string
    {
        // No label/form-group wrapper — the legend is the grouping element itself.
        return $this->renderInput($ctx);
    }
}
