<?php

namespace Redaxo\Core\Content\ExtensionPoint;

use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * @extends ExtensionPoint<string>
 */
class ArticleContentUpdated extends ExtensionPoint
{
    public const NAME = 'ART_CONTENT_UPDATED';

    private Article $article;
    private string $action;

    /** @param array<string, mixed> $params */
    public function __construct(Article $article, string $action, string $subject = '', array $params = [], bool $readonly = false)
    {
        // for BC 'simple' attach params
        $params['article_id'] = $article->id;
        $params['clang'] = $article->clangId;

        parent::__construct(self::NAME, $subject, $params, $readonly);

        $this->article = $article;
        $this->action = $action;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
