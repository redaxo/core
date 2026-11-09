<?php

namespace Redaxo\Core\View\Component;

use Redaxo\Core\Base\UrlProviderInterface;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/** Page links of a {@see Pager} with the number of rows; nothing when everything fits on one page. */
final class Pagination implements Renderable
{
    use RendersView;

    public function __construct(
        public Pager $pager,
        /** Builds the page urls */
        public UrlProviderInterface $urlProvider,
    ) {}
}
