<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\RexVar\MediaListVar;
use Redaxo\Core\RexVar\MediaVar;

use function array_filter;
use function array_values;
use function explode;

/**
 * Media picker (REX_MEDIA_WIDGET), stored as a text column.
 *
 * With `multiple: true` several media may be selected (comma-separated filenames).
 */
class MediaField extends MetaField
{
    /** Ensures a unique widget id per rendered widget on a page. */
    private static int $widgetCounter = 0;

    /** @param list<string> $types restrict the picker to these file extensions, e.g. `['jpg', 'png']` */
    public function __construct(
        string $name,
        string $label,
        public readonly bool $multiple = false,
        /** Restrict to media of the given category id. */
        public readonly ?int $category = null,
        public readonly array $types = [],
        public readonly bool $preview = false,
        ?string $note = null,
        bool $required = false,
    ) {
        parent::__construct($name, $label, $note, $required);
    }

    public function column(MetaEntity $entity): ?Column
    {
        // A single filename fits a varchar; multiple filenames are pipe-delimited and need a text column.
        return $this->multiple
            ? Column::text($this->columnName($entity), nullable: true)
            : Column::varchar($this->columnName($entity), 255, nullable: true);
    }

    /** @return string|list<string> the filename, or the list of filenames for `multiple` */
    public function format(mixed $stored): string|array
    {
        if ($this->multiple) {
            return array_values(array_filter(explode(',', (string) $stored), static fn (string $f): bool => '' !== $f));
        }

        return (string) $stored;
    }

    public function renderInput(MetaContext $context): string
    {
        $category = $this->category ?? $context->mediaCategory?->id;

        $name = $this->columnName($context->entity);
        $id = ++self::$widgetCounter;
        $value = (string) $context->value($this);

        return $this->multiple
            ? MediaListVar::getWidget($id, $name, $value, $category, $this->types, $this->preview)
            : MediaVar::getWidget($id, $name, $value, $category, $this->types, $this->preview);
    }
}
