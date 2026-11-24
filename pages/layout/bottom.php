<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Layout\Footer;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\View\Fragment;

$curPage = Controller::requireCurrentPageObject();

if (!$curPage->hasLayout()) {
    return;
}

?>

</main></div><?php

echo '</div>';

if ('login' !== Controller::getCurrentPage()) {
    echo new Footer(Timer::sinceRequestStart()->getFormattedDelta(Timer::SEC))->render();
}

$bottomfragment = new Fragment();
echo $bottomfragment->parse('core/bottom.php');
unset($bottomfragment);
