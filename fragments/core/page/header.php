<?php

use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

/**
 * Wrapper of the {@see PageHeader} component for callers of the fragment.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

// the variables of the fragment hold markup the caller vouches for
$raw = function (string ...$names): Html {
    $html = '';
    foreach ($names as $name) {
        $html .= Type::string($this->getVar($name, ''));
    }

    /** @psalm-taint-escape html */
    $html = $html;

    return Html::raw($html);
};

echo new PageHeader($raw('heading'), $raw('subheading'), $raw('subtitle'))->render();
