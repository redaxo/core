<?php
/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
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
                        <?php if ($this->getVar('allow_rollback')): ?>
                        <button class="btn btn-apply" data-history-layer="snap"><?= rex_i18n::msg($this->getVar('version_available') ? 'structure_history_snapshot_live_reactivate' : 'structure_history_snapshot_reactivate') ?></button>
                        <?php endif ?>
                        <button class="btn btn-abort" data-history-layer="cancel"><?= rex_i18n::msg('structure_history_close') ?></button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="history-layer-background"></div>

</div>
