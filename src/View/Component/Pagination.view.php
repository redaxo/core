<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Pagination;

use function Redaxo\Core\View\escape;

return static function (Pagination $pagination): void {
    $pager = $pagination->pager;
    if ($pager->getRowCount() <= $pager->getRowsPerPage()) {
        return;
    }

    $url = static fn (int $page): string => escape($pagination->urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($page)]));
    $firstPage = $pager->getFirstPage();
    $lastPage = $pager->getLastPage();
    $from = max($firstPage + 1, $pager->getCurrentPage() - 3);
    $to = min($lastPage - 1, $from + 6);
    $from = max($firstPage + 1, $to - 6);
    $from = $firstPage + 2 == $from ? $firstPage + 1 : $from;
    $to = $lastPage - 2 == $to ? $lastPage - 1 : $to;

    $page = static fn (int $page): string => '<li class="rex-page' . ($pager->isActivePage($page) ? ' active' : '') . '"><a href="' . $url($page) . '">' . ($page + 1) . '</a></li>' ?>
    <nav class="rex-nav-pagination">
        <ul class="pagination">
            <li class="rex-prev<?= $pager->isActivePage($firstPage) ? ' disabled' : '' ?>">
                <a href="<?= $url($pager->getPrevPage()) ?>" title="<?= I18n::msg('list_previous') ?>"><i class="rex-icon rex-icon-previous"></i><span class="sr-only"><?= I18n::msg('list_previous') ?></span></a>
            </li>
            <?= $page($firstPage) ?>
            <?php if ($from > $firstPage + 1): ?>
                <li class="disabled"><span>…</span></li>
            <?php endif ?>
            <?php for ($i = $from; $i <= $to; ++$i): ?>
                <?= $page($i) ?>
            <?php endfor ?>
            <?php if ($to < $lastPage - 1): ?>
                <li class="disabled"><span>…</span></li>
            <?php endif ?>
            <?= $page($lastPage) ?>
            <li class="rex-next<?= $pager->isActivePage($lastPage) ? ' disabled' : '' ?>">
                <a href="<?= $url($pager->getNextPage()) ?>" title="<?= I18n::msg('list_next') ?>"><span class="sr-only"><?= I18n::msg('list_next') ?></span><i class="rex-icon rex-icon-next"></i></a>
            </li>
        </ul>
        <div class="rex-pagination-count"><?= I18n::msg('list_rows_found', $pager->getRowCount()) ?></div>
    </nav>
<?php };
