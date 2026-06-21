<?php

namespace Redaxo\Core\MetaInfo;

use Redaxo\Core\Core;

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
    case Clang;

    /** Column-name prefix, including the trailing underscore. */
    public function prefix(): string
    {
        return match ($this) {
            self::Article => 'art_',
            self::Category => 'cat_',
            self::Media => 'med_',
            self::Clang => 'clang_',
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Article, self::Category => Core::getTable('article'),
            self::Media => Core::getTable('media'),
            self::Clang => Core::getTable('clang'),
        };
    }
}
