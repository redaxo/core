<?php

namespace Project\Template;

use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Template;

#[AsTemplate('default', 'Default')]
final class DefaultTemplate extends Template
{
    #[Override]
    public function render(ArticleContent $content): string
    {
        return $content->getArticle();
    }

    #[Override]
    public function getContentSections(): array
    {
        return [
            new ContentSection(1, 'ctype1'),
            new ContentSection(2, 'ctype2'),
        ];
    }
}
