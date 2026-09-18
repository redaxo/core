<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\MetaInfo\Field\MetaField;

/**
 * The entity a set of meta fields belongs to.
 *
 * Carries the tables the fields of that entity are stored in.
 */
enum MetaEntity
{
    case Article;
    case Category;
    case Media;
    case Language;

    /** @return non-empty-string */
    public function table(): string
    {
        return match ($this) {
            self::Article => 'rex_article',
            self::Category => 'rex_category',
            self::Media => 'rex_media',
            self::Language => 'rex_language',
        };
    }

    /**
     * The table holding the per-language values, or `null` if the entity has no translation table.
     *
     * @return non-empty-string|null
     */
    public function translationTable(): ?string
    {
        return match ($this) {
            self::Article => 'rex_article_translation',
            self::Category => 'rex_category_translation',
            self::Media => 'rex_media_translation',
            self::Language => null,
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
