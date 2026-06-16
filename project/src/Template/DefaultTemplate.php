<?php

namespace Project\Template;

use Override;
use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Content\AsTemplate;
use Redaxo\Core\Content\Template;
use Redaxo\Core\View\HasView;

#[AsTemplate('default', 'Default')]
final class DefaultTemplate extends Template
{
    use HasView;

    #[Override]
    public function render(ArticleContent $content): string
    {
        // The markup lives in the co-located DefaultTemplate.view.php; the article's content object
        // is handed to it. Build out the <head>, navigation, asset handling etc. over there.
        return $this->renderView($content);
    }
}
