<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Content\Category;
use Redaxo\Core\Language\Language;

use function Redaxo\Core\View\escape;

/**
 * REX_CATEGORY[xzy]
 * REX_CATEGORY[field=xzy]
 * REX_CATEGORY[field=xzy id=3]
 * REX_CATEGORY[field=xzy id=3 clang=2].
 *
 * Attribute:
 *   - field    => Feld der Kategorie, das ausgegeben werden soll
 *   - clang    => ClangId der Kategorie
 */
class CategoryVar extends RexVar
{
    /** Werte für die Ausgabe. */
    protected function getOutput()
    {
        $field = $this->getParsedArg('field');
        if (!$field) {
            return false;
        }

        $categoryId = $this->getParsedArg('id', '$this->getValue(\'category_id\')');
        $clang = $this->getParsedArg('clang', 'null');

        return self::class . '::getCategoryValue(' . $categoryId . ', ' . $field . ', ' . $clang . ')';
    }

    /** @return string|int|null */
    public static function getCategoryValue($id, $field, $clang = null)
    {
        if (null === $clang) {
            $clang = Language::getCurrentId();
        }
        $cat = Category::get($id, $clang);
        if ($cat) {
            return escape($cat->getValue($field));
        }

        return null;
    }
}
