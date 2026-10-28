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

    $page = static fn (int $page): string => $pager->isActivePage($page)
        ? '<li class="rex-page active"><a href="' . $url($page) . '" aria-current="page">' . ($page + 1) . '</a></li>'
        : '<li class="rex-page"><a href="' . $url($page) . '">' . ($page + 1) . '</a></li>';
    // at the first or last page, the step is no link
    $step = static fn (string $class, bool $disabled, int $target, string $label, string $icon): string => '<li class="' . $class . ($disabled ? ' disabled' : '') . '">'
        . ($disabled ? '<span aria-disabled="true">' : '<a href="' . $url($target) . '">')
        . '<i class="rex-icon ' . $icon . '" aria-hidden="true"></i><span class="sr-only">' . I18n::msg($label) . '</span>'
        . ($disabled ? '</span>' : '</a>')
        . '</li>' ?>
    <nav class="rex-nav-pagination" aria-label="<?= I18n::msg('navigation_pagination') ?>">
        <ul class="pagination">
            <?= $step('rex-prev', $pager->isActivePage($firstPage), $pager->getPrevPage(), 'list_previous', 'rex-icon-previous') ?>
            <?= $page($firstPage) ?>
            <?php if ($from > $firstPage + 1): ?>
                <li class="disabled"><span aria-hidden="true">…</span></li>
            <?php endif ?>
            <?php for ($i = $from; $i <= $to; ++$i): ?>
                <?= $page($i) ?>
            <?php endfor ?>
            <?php if ($to < $lastPage - 1): ?>
                <li class="disabled"><span aria-hidden="true">…</span></li>
            <?php endif ?>
            <?= $page($lastPage) ?>
            <?= $step('rex-next', $pager->isActivePage($lastPage), $pager->getNextPage(), 'list_next', 'rex-icon-next') ?>
        </ul>
        <div class="rex-pagination-count"><?= I18n::msg('list_rows_found', $pager->getRowCount()) ?></div>
    </nav>
<?php };
