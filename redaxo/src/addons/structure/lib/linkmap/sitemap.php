<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_sitemap_category_tree extends rex_linkmap_tree_renderer
{
    public function __construct(
        private rex_context $context,
    ) {}

    public function getTree($categoryId)
    {
        // if not, let the structure as is, by providing a remembered id
        if ($categoryId <= 0) {
            $categoryId = rex_request::session('tree_category_id', 'int');
        } else {
            rex_request::setSession('tree_category_id', $categoryId);
        }
        return parent::getTree($categoryId);
    }

    /** @return string */
    protected function treeItem(rex_category $cat, $liClasses, $linkClasses, $subHtml, $liIcon)
    {
        $linkClasses .= '';

        if ('' != $liClasses) {
            $liClasses = ' class="' . rtrim($liClasses) . '"';
        }

        if ('' != $linkClasses) {
            $linkClasses = ' class="' . rtrim($linkClasses) . '"';
        }

        $label = self::formatLabel($cat);

        $li = '';
        $li .= '<li' . $liClasses . ' data-cat-id="' . $cat->getId() . '" data-parent-id="' . $cat->getParentId() . '" data-priority="' . $cat->getPriority() . '">';
        $li .= '<a' . $linkClasses . ' href="' . $this->context->getUrl(['toggle_category_id' => $cat->getId()] + rex_api_sitemap_tree::getUrlParams()) . '">&nbsp;</a>';
        $li .= '<a href="' . $this->context->getUrl(['category_id' => $cat->getId()]) . '">' . rex_escape($label) . '<span class="list-item-suffix">' . $cat->getId() . '</span></a>';
        $li .= $subHtml;
        $li .= '</li>';

        return $li;
    }
}
