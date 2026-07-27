<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

echo View::title('Debug AddOn');
echo Message::info(I18n::msg('debug_requires_dev_mode'));
