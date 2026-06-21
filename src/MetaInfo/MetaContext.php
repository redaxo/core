<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\Content\Category;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MetaInfo\Field\MetaField;

use function array_key_exists;

/**
 * Per-item context handed to every {@see MetaField} when rendering, parsing or restricting it.
 *
 * Built once per edited item by the handler, so fields stay entity-agnostic at construction time.
 */
final readonly class MetaContext
{
    /**
     * @param object|null $subject The value object being edited (article/category/media/clang), or `null` when
     *     adding. The concrete types share no common interface, so this is a bare `object`; narrow it with
     *     `instanceof` in {@see MetaField::isAllowed()} when needed.
     * @param Category|null $category Surrounding content category (an article's category / a category's parent);
     *     `null` means root. Set for the Article and Category entities, also available when adding.
     * @param MediaCategory|null $mediaCategory Surrounding media category; `null` means root. Set for the Media entity.
     * @param array<string, int|string|null> $valueOverride pre-resolved values (keyed by column name) that take
     *     precedence over the subject — used to redisplay freshly submitted values after a save
     */
    public function __construct(
        public MetaEntity $entity,
        public ?object $subject = null,
        public ?Category $category = null,
        public ?MediaCategory $mediaCategory = null,
        private array $valueOverride = [],
    ) {}

    /** Current value of the given field: an override if present, otherwise the subject's value, else the default. */
    public function value(MetaField $field): string|int|float|bool|null
    {
        $column = $field->columnName($this->entity);

        if (array_key_exists($column, $this->valueOverride)) {
            return $this->valueOverride[$column];
        }

        if (null === $this->subject) {
            return $field->default;
        }

        // The value objects (Sql/Article/Category/Media) share no common interface yet; this is the single
        // place that reads through the bare object.
        /** @psalm-suppress MixedReturnStatement, MixedMethodCall */
        return $this->subject->getValue($column);
    }
}
