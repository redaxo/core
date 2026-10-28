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

    $page = static fn (int $page): string => '<li><a class="rex-pagination-link" href="' . $url($page) . '"' . ($pager->isActivePage($page) ? ' aria-current="page"' : '') . '>' . ($page + 1) . '</a></li>';
    // at the first or last page, the step is no link
    $step = static fn (bool $disabled, int $target, string $label, string $icon): string => '<li>'
        . ($disabled ? '<span class="rex-pagination-link" aria-disabled="true">' : '<a class="rex-pagination-link" href="' . $url($target) . '">')
        . '<i class="rex-icon ' . $icon . '" aria-hidden="true"></i><span class="rex-visually-hidden">' . I18n::msg($label) . '</span>'
        . ($disabled ? '</span>' : '</a>')
        . '</li>';
    $gap = '<li><span class="rex-pagination-link" aria-hidden="true">…</span></li>' ?>
    <nav class="rex-pagination" aria-label="<?= I18n::msg('navigation_pagination') ?>">
        <ul class="rex-pagination-list" role="list">
            <?= $step($pager->isActivePage($firstPage), $pager->getPrevPage(), 'list_previous', 'rex-icon-previous') ?>
            <?= $page($firstPage) ?>
            <?= $from > $firstPage + 1 ? $gap : '' ?>
            <?php for ($i = $from; $i <= $to; ++$i): ?>
                <?= $page($i) ?>
            <?php endfor ?>
            <?= $to < $lastPage - 1 ? $gap : '' ?>
            <?= $page($lastPage) ?>
            <?= $step($pager->isActivePage($lastPage), $pager->getNextPage(), 'list_next', 'rex-icon-next') ?>
        </ul>
        <p class="rex-pagination-count"><?= I18n::msg('list_rows_found', $pager->getRowCount()) ?></p>
    </nav>
<?php };
