<?php

use Redaxo\Core\Tests\View\Fixtures\Card;
use Redaxo\Core\View\Html;

use function Redaxo\Core\View\escape;

return static function (Card $card): void { ?>
    <article class="card">
        <header class="card-header"><?= escape($card->title) ?></header>
        <div class="card-body">
            <?php if (null !== $card->body): ?>
                <?= Html::from($card->body) ?>
            <?php else: ?>
                <em>No content yet.</em>
            <?php endif ?>
        </div>
        <?php if (null !== $card->footer): ?>
            <footer class="card-footer"><?= Html::from($card->footer) ?></footer>
        <?php endif ?>
    </article>
<?php };
