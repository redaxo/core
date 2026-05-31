<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Category;
use Redaxo\Core\Http\Context;

use function count;
use function Redaxo\Core\View\escape;

/**
 * @internal
 */
class CategoryTree extends CategoryTreeRenderer
{
    public function __construct(
        private Context $context,
    ) {}

    /** @return string */
    protected function treeItem(Category $cat, $liClasses, $linkClasses, $subHtml, $liIcon)
    {
        if ('' != $liClasses) {
            $liClasses = ' class="' . rtrim($liClasses) . '"';
        }

        if ('' != $linkClasses) {
            $linkClasses = ' class="' . rtrim($linkClasses) . '"';
        }

        $label = self::formatLabel($cat);

        $countChildren = count($cat->getChildren());
        $badgeCat = ($countChildren > 0) ? '<span class="badge">' . $countChildren . '</span>' : '';
        $li = '';
        $li .= '<li' . $liClasses . '>';
        $li .= '<a' . $linkClasses . ' href="' . $this->context->getUrl(['category_id' => $cat->id]) . '">' . $liIcon . escape($label) . '<span class="list-item-suffix">' . $cat->id . '</span></a>';
        $li .= $badgeCat;
        $li .= $subHtml;
        $li .= '</li>' . "\n";

        return $li;
    }
}
