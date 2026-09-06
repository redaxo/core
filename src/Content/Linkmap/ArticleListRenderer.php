<?php

namespace Redaxo\Core\Content\Linkmap;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;

use function count;

/**
 * @internal
 */
abstract class ArticleListRenderer
{
    public function getList(int $categoryId): string
    {
        $isRoot = 0 === $categoryId;

        $user = Core::requireUser();
        $mountpoints = $user->hasPerm('linkmap[all_categories]') ? [] : $user->getComplexPerm('structure')->getMountpoints();

        if ($isRoot && 1 === count($mountpoints)) {
            $categoryId = reset($mountpoints);
            $isRoot = false;
        }

        if ($isRoot && 0 == count($mountpoints)) {
            $articles = Article::getRootArticles();
        } elseif ($isRoot) {
            $articles = [];
        } else {
            $articles = Category::get($categoryId)->getArticles();
        }
        return self::renderList($articles, $categoryId);
    }

    /** @param list<Article> $articles */
    public function renderList(array $articles, int $categoryId): string
    {
        $list = '';
        if ($articles) {
            foreach ($articles as $article) {
                $list .= $this->listItem($article, $categoryId);
            }

            if ('' != $list) {
                $list = '<ul class="list-group rex-linkmap-list-group">' . $list . '</ul>';
            }
        }
        return $list;
    }

    abstract protected function listItem(Article $article, int $categoryId): string;
}
