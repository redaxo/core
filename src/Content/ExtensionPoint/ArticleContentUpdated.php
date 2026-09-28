<?php

namespace Redaxo\Core\Content\ExtensionPoint;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;
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
        /** Affected slice of the `slice_*` actions (for `slice_deleted` its state before deletion) */
        public readonly ?ArticleSlice $slice = null,
        string $subject = '',
        array $params = [],
        bool $readonly = false,
    ) {
        // for BC 'simple' attach params
        $params['article_id'] = $article->id;
        $params['language'] = $article->languageId;

        parent::__construct(self::NAME, $subject, $params, $readonly);
    }
}
