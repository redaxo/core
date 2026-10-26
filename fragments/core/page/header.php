<?php

use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

/**
 * Wrapper of the {@see PageHeader} component for callers of the fragment.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

echo new PageHeader(
    Html::raw((string) $this->getVar('heading', '')),
    Html::raw((string) $this->getVar('subheading', '')),
    Html::raw((string) $this->getVar('subtitle', '')),
)->render();
