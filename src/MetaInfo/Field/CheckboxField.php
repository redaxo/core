<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;
use function sprintf;

/** A single boolean checkbox, stored as 0/1. */
class CheckboxField extends MetaField
{
    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'tinyint(1)', nullable: false, default: '0');
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        return Request::post($this->columnName($context->entity), 'bool', false) ? 1 : 0;
    }

    public function format(mixed $stored): bool
    {
        return (bool) $stored;
    }

    public function renderInput(MetaContext $context): string
    {
        $name = $this->columnName($context->entity);

        $attributes = new HtmlAttributes([
            'type' => 'checkbox',
            'name' => $name,
            'id' => $name,
            'value' => '1',
            'checked' => (bool) $context->value($this),
        ]);

        return sprintf('<input %s>', $attributes);
    }

    public function render(MetaContext $context): string
    {
        $note = null !== $this->note
            ? sprintf('<p class="help-block rex-note">%s</p>', escape($this->note))
            : '';

        return sprintf(
            '<div class="checkbox"><label>%s %s</label>%s</div>',
            $this->renderInput($context),
            escape($this->label),
            $note,
        );
    }
}
