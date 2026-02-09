<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
abstract class rex_linkmap_tree_renderer
{
    /**
     * @return string
     */
    public function getTree($categoryId)
    {
        $category = rex_category::get($categoryId);

        $user = rex::requireUser();

        // If the user has the new permission 'linkmap[all_categories]' show full tree
        $mountpoints = $user->hasPerm('linkmap[all_categories]') ? [] : $user->getComplexPerm('structure')->getMountpointCategories();
        if (count($mountpoints) > 0) {
            $roots = $mountpoints;
            if (!$category && 1 === count($roots)) {
                $category = $roots[0];
            }
        } else {
            $roots = rex_category::getRootCategories();
        }

        $tree = [];
        if ($category) {
            foreach ($category->getParentTree() as $cat) {
                $tree[] = $cat->getId();
            }
        }

        $rendered = $this->renderTree($roots, $tree);
        // add css class to root node
        return '<ul class="list-group rex-linkmap-list-group"' . substr($rendered, 3);
    }

    /**
     * Returns the markup of a tree structure, with $children as root categories and respecing $activeTreeIds as the active path.
     *
     * @param list<rex_category> $children A array of rex_category objects representing the top level objects
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
            $catId = $cat->getId();
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
            $ul = '<ul class="list-group" data-cat-id="' . $children[0]->getParentId() . '">' . "\n" . $li . '</ul>' . "\n";
        }

        return $ul;
    }

    /**
     * @return string
     */
    abstract protected function treeItem(rex_category $cat, $liClasses, $linkClasses, $subHtml, $liIcon);

    /**
     * @return string
     */
    public static function formatLabel(rex_structure_element $OOobject)
    {
        $label = $OOobject->getName();

        if ('' == trim($label)) {
            $label = '&nbsp;';
        }

        if ($OOobject instanceof rex_article && !$OOobject->hasTemplate()) {
            $label .= ' [' . rex_i18n::msg('linkmap_has_no_template') . ']';
        }

        return $label;
    }

    /**
     * @return string
     */
    public static function formatLi(rex_structure_element $OOobject, $currentCategoryId, rex_context $context, $liAttr = '', $linkAttr = '')
    {
        $linkAttr .= ' class="' . ($OOobject->isOnline() ? 'rex-online' : 'rex-offline') . '"';

        if (!str_contains($linkAttr, ' href=')) {
            $linkAttr .= ' href="' . $context->getUrl(['category_id' => $OOobject->getId()]) . '"';
        }

        $label = self::formatLabel($OOobject);

        $icon = '<i class="rex-icon rex-icon-' . ($OOobject->isSiteStartArticle() ? 'sitestartarticle' : ($OOobject->isStartArticle() ? 'startarticle' : 'article')) . '"></i>';

        return '<li' . $liAttr . '><a' . $linkAttr . '>' . $icon . ' ' . rex_escape($label) . '<span class="list-item-suffix">' . $OOobject->getId() . '</span></a>';
    }
}

/**
 * @package redaxo\structure
 *
 * @internal
 */
abstract class rex_linkmap_article_list_renderer
{
    /**
     * @return string
     */
    public function getList($categoryId)
    {
        $isRoot = 0 === $categoryId;

        $user = rex::requireUser();
        $mountpoints = $user->hasPerm('linkmap[all_categories]') ? [] : $user->getComplexPerm('structure')->getMountpoints();

        // If the user is allowed to see all categories, treat as if there are no mountpoints
        if ($isRoot && 1 === count($mountpoints)) {
            $categoryId = reset($mountpoints);
            $isRoot = false;
        }

        if ($isRoot && 0 == count($mountpoints)) {
            $articles = rex_article::getRootArticles();
        } elseif ($isRoot) {
            $articles = [];
        } else {
            $articles = rex_category::get($categoryId)->getArticles();
        }
        return self::renderList($articles, $categoryId);
    }

    /**
     * @return string
     */
    public function renderList(array $articles, $categoryId)
    {
        $list = '';
        if ($articles) {
            foreach ($articles as $article) {
                $list .= $this->listItem($article, $categoryId);
            }

            if ('' != $list) {
                $list = '<ul class="list-group rex-linkmap-list-group list-with-icon">' . $list . '</ul>';
            }
        }
        return $list;
    }

    /**
     * @return string
     */
    abstract protected function listItem(rex_article $article, $categoryId);
}
