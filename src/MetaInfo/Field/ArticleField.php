<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\RexVar\LinkListVar;
use Redaxo\Core\RexVar\LinkVar;

/**
 * Article/category picker (REX_LINK_WIDGET), stored as a text column.
 *
 * With `multiple: true` several articles may be selected (pipe-delimited article ids).
 */
class ArticleField extends MetaField
{
    /** Ensures a unique widget id per rendered widget on a page. */
    private static int $widgetCounter = 0;

    public function __construct(
        string $name,
        string $label,
        public readonly bool $multiple = false,
        /** Pre-select this category in the picker. */
        public readonly ?int $category = null,
        ?string $note = null,
        bool $required = false,
    ) {
        parent::__construct($name, $label, $note, $required);
    }

    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'text', nullable: true);
    }

    public function renderInput(MetaContext $context): string
    {
        $category = $this->category ?? $context->category?->id;

        $args = [];
        if (null !== $category) {
            $args['category'] = $category;
        }

        $name = $this->columnName($context->entity);
        $id = ++self::$widgetCounter;
        $value = (string) $context->value($this);

        if ($this->multiple) {
            return LinkListVar::getWidget($id, $name, $value, $args);
        }

        return LinkVar::getWidget($id, $name, '' === $value ? null : (int) $value, $args);
    }
}
