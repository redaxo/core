<?php

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_linkmap_category_tree extends rex_linkmap_tree_renderer
{
    public function __construct(
        private rex_context $context,
    ) {}

    /** @return string */
    protected function treeItem(rex_category $cat, $liClasses, $linkClasses, $subHtml, $liIcon)
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
        $li .= '<a' . $linkClasses . ' href="' . $this->context->getUrl(['category_id' => $cat->getId()]) . '">' . $liIcon . rex_escape($label) . '<span class="list-item-suffix">' . $cat->getId() . '</span></a>';
        $li .= $badgeCat;
        $li .= $subHtml;
        $li .= '</li>' . "\n";

        return $li;
    }
}

/**
 * @package redaxo\structure
 *
 * @internal
 */
class rex_linkmap_article_list extends rex_linkmap_article_list_renderer
{
    public function __construct(
        private rex_context $context,
    ) {}

    /** @return string */
    protected function listItem(rex_article $article, $categoryId)
    {
        $liAttr = ' class="list-group-item rex-linkmap-list-item-article"';
        $url = 'javascript:insertLink(\'redaxo://' . $article->getId() . '\',\'' . rex_escape(trim(sprintf('%s [%s]', $article->getName(), $article->getId())), 'js') . '\');';
        return rex_linkmap_tree_renderer::formatLi($article, $categoryId, $this->context, $liAttr, ' href="' . $url . '"') . '</li>' . "\n";
    }
}
