<?php

namespace Project\Template;

use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;

use function Redaxo\Core\View\escape;

#[AsTemplate('default', 'Default')]
final class DefaultTemplate extends Template
{
    #[Override]
    public function render(ArticleContent $content): string
    {
        // Minimal starting point: a bare HTML document wrapped around the article's content.
        // In a real project you'll usually move the markup into a fragment (`fragments/`, rendered via `Fragment`)
        // and build out the <head>, navigation, asset handling etc. there — this method then just glues the parts together.

        $title = escape($content->article->name);
        $siteName = escape(Core::getServerName());
        $content = $content->renderContent();

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>$title – $siteName</title>
            </head>
            <body>
            $content
            </body>
            </html>
            HTML;
    }
}
