<?php

use Redaxo\Core\Tests\View\Fixtures\Greeting;

use function Redaxo\Core\View\escape;

// Alternative, signature-compatible view registered as an override for Greeting in the tests.
return static function (Greeting $greeting): void { ?>
    <div class="greeting-override">OVERRIDDEN: <?= escape($greeting->name) ?></div>
<?php };
