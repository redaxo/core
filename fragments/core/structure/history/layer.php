<?php
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<div id="content-history-layer" class="history-layer">

    <div class="history-layer-content">
        <div class="history-layer-layout">

            <div class="history-layer-panel-1 hidden-xs">
                <div id="history-layer-slider" class="history-layer-slider"></div>
            </div>

            <div class="history-layer-panel-2">
                <div class="row">
                    <div class="col-lg-6 text-center hidden-xs hidden-sm hidden-md">
                        <div class="btn-group history-select-group">
                            <div class="rex-select-style"><?= $this->getVar('content1select') ?></div>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <div class="btn-group history-select-group">
                            <button class="btn btn-default" data-history-layer="prev"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                            <div class="rex-select-style"><?= $this->getVar('content2select') ?></div>
                            <button class="btn btn-default" data-history-layer="next"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-layer-panel-3">
                <div class="row">
                    <div class="col-lg-6 hidden-xs hidden-sm hidden-md">
                        <div class="history-responsive-container">
                            <?= $this->getVar('content1iframe') ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="history-responsive-container">
                            <?= $this->getVar('content2iframe') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-layer-panel-4">
                <div class="row">
                    <div class="col-lg-6 col-lg-push-6 text-center">
                        <button class="btn btn-apply" data-history-layer="snap"><?= I18n::msg('structure_history_snapshot_reactivate') ?></button>
                        <button class="btn btn-abort" data-history-layer="cancel"><?= I18n::msg('structure_history_close') ?></button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="history-layer-background"></div>

</div>
