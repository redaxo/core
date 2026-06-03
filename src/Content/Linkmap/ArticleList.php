<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Article;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * @internal
 */
final class ArticleList extends ArticleListRenderer
{
    /** @return string */
    protected function listItem(Article $article, $categoryId)
    {
        $url = 'javascript:insertLink(\'redaxo://' . $article->id . '\',\'' . escape(trim(sprintf('%s [%s]', $article->name, $article->id)), 'js') . '\');';

        $linkClass = $article->isOnline() ? 'rex-online' : 'rex-offline';
        $label = CategoryTreeRenderer::formatLabel($article);

        $iconType = match (true) {
            $article->isSiteStartArticle() => 'sitestartarticle',
            $article->isStartArticle() => 'startarticle',
            default => 'article',
        };

        return '<li class="list-group-item rex-linkmap-list-item-article">'
            . '<a href="' . $url . '" class="' . $linkClass . '">'
            . '<i class="rex-icon rex-icon-' . $iconType . '"></i> '
            . escape($label)
            . '<span class="list-item-suffix">' . $article->id . '</span>'
            . '</a>'
            . '</li>' . "\n";
    }
}
