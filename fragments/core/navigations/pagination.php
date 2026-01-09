<?php

use Redaxo\Core\Base\UrlProviderInterface;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<?php
    /** @var Pager $pager */
    $pager = $this->pager;
    /** @var UrlProviderInterface $urlProvider */
    $urlProvider = $this->urlprovider;

    $firstPage = $pager->getFirstPage();
    $lastPage = $pager->getLastPage();

    $from = max($firstPage + 1, $pager->getCurrentPage() - 3);
    $to = min($lastPage - 1, $from + 6);
    $from = max($firstPage + 1, $to - 6);
    $from = $firstPage + 2 == $from ? $firstPage + 1 : $from;
    $to = $lastPage - 2 == $to ? $lastPage - 1 : $to;
?>
        <?php if ($pager->getRowCount() > $pager->getRowsPerPage()): ?>
            <nav class="rex-nav-pagination">
                <ul class="pagination">
                    <li class="rex-prev<?= $pager->isActivePage($firstPage) ? ' disabled' : '' ?>">
                        <a href="<?= $urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($pager->getPrevPage())]) ?>" title="<?= $this->i18n('list_previous') ?>">
                            <i class="rex-icon rex-icon-previous"></i><span class="sr-only"><?= $this->i18n('list_previous') ?></span>
                        </a>
                    </li>

                    <li class="rex-page<?= $pager->isActivePage($firstPage) ? ' active' : '' ?>">
                        <a href="<?= $urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($firstPage)]) ?>">
                            <?= $firstPage + 1 ?>
                        </a>
                    </li>

                    <?php if ($from > $firstPage + 1): ?>
                        <li class="disabled">
                            <span>…</span>
                        </li>
                    <?php endif ?>

                    <?php for ($page = $from; $page <= $to; ++$page): ?>
                        <li class="rex-page<?= $pager->isActivePage($page) ? ' active' : '' ?>">
                            <a href="<?= $urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($page)]) ?>">
                                <?= $page + 1 ?>
                            </a>
                        </li>
                    <?php endfor ?>

                    <?php if ($to < $lastPage - 1): ?>
                        <li class="disabled">
                            <span>…</span>
                        </li>
                    <?php endif ?>

                    <li class="rex-page<?= $pager->isActivePage($lastPage) ? ' active' : '' ?>">
                        <a href="<?= $urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($lastPage)]) ?>">
                            <?= $lastPage + 1 ?>
                        </a>
                    </li>

                    <li class="rex-next<?= $pager->isActivePage($lastPage) ? ' disabled' : '' ?>">
                        <a href="<?= $urlProvider->getUrl([$pager->getCursorName() => $pager->getCursor($pager->getNextPage())]) ?>" title="<?= $this->i18n('list_next') ?>">
                            <span class="sr-only"><?= $this->i18n('list_next') ?></span><i class="rex-icon rex-icon-next"></i>
                        </a>
                    </li>
                </ul>
                <div class="rex-pagination-count"><?= $this->i18n('list_rows_found', $pager->getRowCount()) ?></div>
            </nav>
        <?php endif;
