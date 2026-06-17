<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\RexVar\MediaListVar;
use Redaxo\Core\RexVar\MediaVar;

use function implode;

/**
 * Media picker (REX_MEDIA_WIDGET), stored as a text column.
 *
 * With `multiple: true` several media may be selected (pipe-delimited filenames).
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
        return new Column($this->columnName($entity), 'text', nullable: true);
    }

    public function renderInput(MetaContext $ctx): string
    {
        $category = $this->category ?? $ctx->mediaCategory?->id;

        $args = [];
        if (null !== $category) {
            $args['category'] = $category;
        }
        if ([] !== $this->types) {
            // the widget expects a comma-separated string in the args query
            $args['types'] = implode(',', $this->types);
        }
        if ($this->preview) {
            $args['preview'] = true;
        }

        $name = $this->columnName($ctx->entity);
        $id = ++self::$widgetCounter;
        $value = (string) $ctx->value($this);

        return $this->multiple
            ? MediaListVar::getWidget($id, $name, $value, $args)
            : MediaVar::getWidget($id, $name, $value, $args);
    }
}
