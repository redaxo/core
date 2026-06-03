<?php

namespace Redaxo\Core\Content\ExtensionPoint;

use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * @extends ExtensionPoint<string>
 */
final class ArticleContentUpdated extends ExtensionPoint
{
    public const string NAME = 'ART_CONTENT_UPDATED';

    /** @param array<string, mixed> $params */
    public function __construct(
        public readonly Article $article,
        public readonly string $action,
        string $subject = '',
        array $params = [],
        bool $readonly = false,
    ) {
        // for BC 'simple' attach params
        $params['article_id'] = $article->id;
        $params['clang'] = $article->clangId;

        parent::__construct(self::NAME, $subject, $params, $readonly);
    }
}
