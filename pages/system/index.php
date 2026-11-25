<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\View;

echo View::title(I18n::msg('system'), meta: 'REDAXO ' . Core::getVersion() . ' · PHP ' . PHP_VERSION);

Controller::includeCurrentPageSubPath();
