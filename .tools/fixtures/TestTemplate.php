<?php

namespace Redaxo\Core\Fixtures;

use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\ContentSection;
use Redaxo\Core\Content\Template;

#[AsTemplate('test', 'Test')]
final class TestTemplate extends Template
{
    #[Override]
    public function render(ArticleContent $content): string
    {
        return $content->renderContent();
    }

    /** @return non-empty-list<ContentSection> */
    #[Override]
    public function getContentSections(): array
    {
        return [
            new ContentSection(1, 'ctype1'),
            new ContentSection(2, 'ctype2'),
        ];
    }
}
