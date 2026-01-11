<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\MetaInfo\MetaInfo;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\View;

$subpage = Controller::getCurrentPagePart(2);

echo View::title(I18n::msg('minfo_title'));

$prefix = match ($subpage) {
    'articles' => 'art_',
    'media' => 'med_',
    'categories' => 'cat_',
    'clangs' => 'clang_',
    default => '',
};

if ('' === $prefix) {
    Controller::includeCurrentPageSubPath();
} else {
    $metaTable = MetaInfo::metaTable($prefix);
    require __DIR__ . '/field.php';
}
