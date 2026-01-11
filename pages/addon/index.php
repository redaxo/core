<?php

use Redaxo\Core\Http\Request;

$subpage = Request::request('subpage', 'string');

if ('' == $subpage) {
    require __DIR__ . '/list.php';
} else {
    require __DIR__ . '/details.php';
}
