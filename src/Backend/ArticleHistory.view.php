<?php

use Redaxo\Core\Backend\ArticleHistory;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Component\Alert;
use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonVariant;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (ArticleHistory $history): void {
    $current = I18n::msg('structure_history_current_version');
    $version = I18n::msg('structure_history_version');

    $base = Html::capture(static function () use ($history, $current): void {
        if (!$history->workVersion) {
            echo escape($current);
            return;
        } ?>
        <select class="rex-input rex-history-select" data-rex-history-base aria-label="<?= escape($current) ?>">
            <option value="<?= ArticleRevision::LIVE ?>"><?= escape($current) ?></option>
            <option value="<?= ArticleRevision::WORK ?>"><?= escape(I18n::msg('version_workingversion')) ?></option>
        </select>
    <?php });

    // newest first like the snapshots, the current version on top; `data-time` places them on the timeline
    $target = Html::capture(static function () use ($history, $current, $version): void { ?>
        <div class="rex-history-switch">
            <?= new Button(I18n::rawMsg('structure_history_older'), icon: 'previous', hiddenLabel: true, attributes: new HtmlAttributes(['data-rex-history-step' => '-1']))->render() ?>
            <select name="history_date" form="rex-history-restore" class="rex-input rex-history-select" data-rex-history-target aria-label="<?= escape($version) ?>">
                <option value="" data-time="<?= time() ?>"><?= escape($current) ?></option>
                <?php foreach ($history->snapshots as $snapshot): ?>
                    <option <?= new HtmlAttributes(['value' => $snapshot['history_date'], 'data-time' => (string) strtotime($snapshot['history_date'])]) ?>>
                        <?= escape(Formatter::intlDateTime($snapshot['history_date']) . ('' === $snapshot['history_user'] ? '' : ' · ' . $snapshot['history_user'])) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?= new Button(I18n::rawMsg('structure_history_newer'), icon: 'next', hiddenLabel: true, attributes: new HtmlAttributes(['data-rex-history-step' => '1']))->render() ?>
        </div>
    <?php });

    // `target` sends the form past the overlay: the restored article opens in the main window
    $restore = null === $history->restoreUrl ? '' : Html::capture(static function () use ($history): void { ?>
        <form id="rex-history-restore" method="post" action="<?= escape($history->restoreUrl) ?>" target="_top">
            <?= Html::raw(CsrfToken::factory('structure_history')->getHiddenField()) ?>
            <?= new Button(I18n::rawMsg('structure_history_snapshot_reactivate'), icon: 'refresh', variant: ButtonVariant::Success, type: 'submit', attributes: new HtmlAttributes([
                'name' => 'func',
                'value' => 'restore',
                'disabled' => true,
            ]))->render() ?>
        </form>
    <?php });

    $show = new Button(I18n::rawMsg('article_show'), url: $history->previewUrl, icon: 'external-link', attributes: new HtmlAttributes([
        'target' => '_blank',
        'rel' => 'noopener',
        'data-rex-history-show' => true,
    ]));

    $frame = static fn (string $title): Html => Html::capture(static function () use ($title): void { ?>
        <div class="rex-history-frame"><iframe title="<?= escape($title) ?>"></iframe></div>
    <?php });
    ?>
    <div class="rex-history" data-rex-history="<?= escape($history->previewUrl) ?>">
        <?php if ($oldest = end($history->snapshots)): ?>
            <div class="rex-history-timeline">
                <div class="rex-history-track" aria-hidden="true"></div>
                <input type="range" class="rex-history-range" min="0" max="1000" aria-label="<?= escape($version) ?>">
                <div class="rex-history-scale" aria-hidden="true">
                    <span><?= escape(Formatter::intlDateTime($oldest['history_date'])) ?></span>
                    <span><?= escape($current) ?></span>
                </div>
            </div>
        <?php else: ?>
            <?= new Alert(I18n::rawMsg('structure_history_no_versions'))->render() ?>
        <?php endif ?>

        <div class="rex-history-previews">
            <?= new Section(
                title: $base,
                content: $frame($current),
                footer: $show,
                attributes: new HtmlAttributes(['class' => ['rex-history-preview'], 'data-version' => 'base']),
            )->render() ?>
            <?= new Section(
                title: $target,
                content: $frame($version),
                footer: $restore,
                variant: SectionVariant::Edit,
                attributes: new HtmlAttributes(['class' => ['rex-history-preview']]),
            )->render() ?>
        </div>
    </div>
<?php };
