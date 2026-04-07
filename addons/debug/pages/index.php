<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

echo View::title('Debug AddOn');
echo Message::info('<a href="' . Url::backendPage('system/settings') . '">' . I18n::msg('debug_activate_debugmode') . '</a>');

echo Message::warning(I18n::msg('debug_mode_note'));
