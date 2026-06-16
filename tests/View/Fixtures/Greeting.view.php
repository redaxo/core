<?php

use Redaxo\Core\Tests\View\Fixtures\Greeting;
use Redaxo\Core\View\Html;

use function Redaxo\Core\View\escape;

return static function (Greeting $greeting): void { ?>
    <section class="greeting">
        <h1>Hello, <?= escape($greeting->name) ?>!</h1>
        <p><?= Html::from($greeting->message) ?></p>
    </section>
<?php };
