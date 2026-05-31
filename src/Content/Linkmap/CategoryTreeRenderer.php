<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructureElement;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;

/**
 * @internal
 */
abstract class CategoryTreeRenderer
{
    /** @return string */
    public function getTree($categoryId)
    {
        $category = Category::get($categoryId);

        $user = Core::requireUser();

        // If the user has the new permission 'linkmap[all_categories]' show full tree
        $mountpoints = $user->hasPerm('linkmap[all_categories]') ? [] : $user->getComplexPerm('structure')->getMountpointCategories();
        if (count($mountpoints) > 0) {
            $roots = $mountpoints;
            if (!$category && 1 === count($roots)) {
                $category = $roots[0];
            }
        } else {
            $roots = Category::getRootCategories();
        }

        $tree = [];
        if ($category) {
            foreach ($category->getParentTree() as $cat) {
                $tree[] = $cat->id;
            }
        }

        $rendered = $this->renderTree($roots, $tree);
        // add css class to root node
        return '<ul class="list-group rex-linkmap-list-group"' . substr($rendered, 3);
    }

    /**
     * Returns the markup of a tree structure, with $children as root categories and respecing $activeTreeIds as the active path.
     *
     * @param list<Category> $children A array of category objects representing the top level objects
     * @param list<int> $activeTreeIds
     *
     * @return string the rendered markup
     */
    public function renderTree(array $children, array $activeTreeIds)
    {
        $ul = '';
        $li = '';
        foreach ($children as $cat) {
            $catChildren = $cat->getChildren();
            $catId = $cat->id;
            $liclasses = 'list-group-item';
            $linkclasses = '';
            $subLi = '';
            $liIcon = '<i class="rex-icon rex-icon-category"></i> ';

            $linkclasses .= $cat->isOnline() ? 'rex-online ' : 'rex-offline ';
            if (in_array($catId, $activeTreeIds)) {
                $subLi = $this->renderTree($catChildren, $activeTreeIds);
                $liIcon = '<i class="rex-icon rex-icon-open-category"></i> ';
                $linkclasses .= 'rex-active ';
            }

            $li .= $this->treeItem($cat, $liclasses, $linkclasses, $subLi, $liIcon);
        }

        if ('' != $li) {
            $ul = '<ul class="list-group" data-cat-id="' . ($children[0]->parentId ?? 0) . '">' . "\n" . $li . '</ul>' . "\n";
        }

        return $ul;
    }

    /** @return string */
    abstract protected function treeItem(Category $cat, $liClasses, $linkClasses, $subHtml, $liIcon);

    public static function formatLabel(StructureElement $OOobject): string
    {
        $label = $OOobject->name;

        if ('' === trim($label)) {
            $label = '&nbsp;';
        }

        if ($OOobject instanceof Article && null === $OOobject->templateKey) {
            $label .= ' [' . I18n::msg('linkmap_has_no_template') . ']';
        }

        return $label;
    }
}
