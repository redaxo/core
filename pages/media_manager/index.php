<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

echo View::title(I18n::msg('media_manager'));

$func = Request::request('func', 'string');
if ('clear_cache' == $func) {
    $c = MediaManager::deleteCache();
    echo Message::info(I18n::msg('media_manager_cache_files_removed', $c));
}

Controller::includeCurrentPageSubPath();
