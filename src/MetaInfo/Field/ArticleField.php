<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\RexVar\LinkListVar;
use Redaxo\Core\RexVar\LinkVar;

use function array_filter;
use function array_map;
use function array_values;
use function explode;

/**
 * Article/category picker (REX_LINK_WIDGET), stored as a text column.
 *
 * With `multiple: true` several articles may be selected (comma-separated article ids).
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
        // A single article is just an id; multiple ids stay short enough for a varchar.
        return $this->multiple
            ? Column::varchar($this->columnName($entity), 255, nullable: true)
            : Column::int($this->columnName($entity), nullable: true);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        $value = Request::post($this->columnName($context->entity), 'string', '');

        if ($this->multiple) {
            return $value;
        }

        return '' === $value ? null : (int) $value;
    }

    /** @return int|list<int>|null the article id, or the list of ids for `multiple` */
    public function format(mixed $stored): int|array|null
    {
        if ($this->multiple) {
            return array_values(array_map(
                static fn (string $id): int => (int) $id,
                array_filter(explode(',', (string) $stored), static fn (string $id): bool => '' !== $id),
            ));
        }

        return null === $stored || '' === $stored ? null : (int) $stored;
    }

    public function renderInput(MetaContext $context): string
    {
        $category = $this->category ?? $context->category?->id;

        $name = $this->columnName($context->entity);
        $id = ++self::$widgetCounter;
        $value = (string) $context->value($this);

        if ($this->multiple) {
            return LinkListVar::getWidget($id, $name, $value, $category);
        }

        return LinkVar::getWidget($id, $name, '' === $value ? null : (int) $value, $category);
    }
}
