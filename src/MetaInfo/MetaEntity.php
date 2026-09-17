<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\MetaInfo\Field\MetaField;

/**
 * The entity a set of meta fields belongs to.
 *
 * Carries the column-name prefix (also used to scope which columns of a table are metainfo-managed)
 * and the target table. Note that {@see self::Article} and {@see self::Category} share the same
 * table (categories are start articles), which is exactly why the prefix must encode the entity.
 */
enum MetaEntity
{
    case Article;
    case Category;
    case Media;
    case Language;

    /** Column-name prefix, including the trailing underscore. */
    public function prefix(): string
    {
        return match ($this) {
            self::Article => 'art_',
            self::Category => 'cat_',
            self::Media => 'med_',
            self::Language => 'lang_',
        };
    }

    /** @return non-empty-string */
    public function table(): string
    {
        return match ($this) {
            self::Article, self::Category => 'rex_article',
            self::Media => 'rex_media',
            self::Language => 'rex_language',
        };
    }

    /**
     * The table holding the per-language values, or `null` if the entity has no translation table.
     *
     * Articles and categories still keep one row per language in their main table, so their translatable fields
     * end up there until the language split.
     *
     * @return non-empty-string|null
     */
    public function translationTable(): ?string
    {
        return match ($this) {
            self::Media => 'rex_media_translation',
            default => null,
        };
    }

    /**
     * The table the given field is stored in.
     *
     * @return non-empty-string
     */
    public function tableForField(MetaField $field): string
    {
        return $field->translatable ? $this->translationTable() ?? $this->table() : $this->table();
    }
}
