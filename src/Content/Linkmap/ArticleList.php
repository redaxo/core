<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Http\Context;

use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * @internal
 */
class ArticleList extends ArticleListRenderer
{
    public function __construct(
        private Context $context,
    ) {}

    /**
     * @return string
     */
    protected function listItem(Article $article, $categoryId)
    {
        $liAttr = ' class="list-group-item rex-linkmap-list-item-article"';
        $url = 'javascript:insertLink(\'redaxo://' . $article->getId() . '\',\'' . escape(trim(sprintf('%s [%s]', $article->getName(), $article->getId())), 'js') . '\');';
        return CategoryTreeRenderer::formatLi($article, $categoryId, $this->context, $liAttr, ' href="' . $url . '"') . '</li>' . "\n";
    }
}
