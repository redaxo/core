<?php

use Redaxo\Core\Content\ArticleContent;
use Redaxo\Core\Core;

use function Redaxo\Core\View\escape;

return static function (ArticleContent $content): void { ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= escape($content->article->name) ?> – <?= escape(Core::getServerName()) ?></title>
    </head>
    <body>
        <?= $content->renderContent() ?>
    </body>
    </html>
<?php };
