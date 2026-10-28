<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Article;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * @internal
 */
final class ArticleList extends ArticleListRenderer
{
    protected function listItem(Article $article, int $categoryId): string
    {
        $linkClass = $article->isOnline() ? 'rex-u-online' : 'rex-u-offline';
        $label = CategoryTreeRenderer::formatLabel($article);

        $iconType = match (true) {
            $article->isSiteStartArticle() => 'sitestartarticle',
            $article->isStartArticle() => 'startarticle',
            default => 'article',
        };

        return '<li class="rex-list-item">'
            . '<button ' . new HtmlAttributes([
                'type' => 'button',
                'class' => $linkClass,
                'data-rex-pick' => (string) $article->id,
                'data-rex-pick-label' => trim(sprintf('%s [%s]', $article->name, $article->id)),
            ])->toString() . '>'
            . '<i class="rex-icon rex-icon-' . $iconType . '" aria-hidden="true"></i> '
            . escape($label)
            . '<span class="rex-list-suffix">' . $article->id . '</span>'
            . '</button>'
            . '</li>' . "\n";
    }
}
