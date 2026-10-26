<?php

use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

return static function (Section $section): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value;
    $variant = $section->variant->value;
    $collapseId = $section->collapsible ? 'rex-section-' . bin2hex(random_bytes(4)) : null;

    // `rex-page-section` and the `panel` classes bridge to the legacy styles of the content until they are replaced
    ?>
    <section <?= $section->attributes->with(['class' => ['rex-section', 'rex-section--' . $variant, 'rex-page-section', 'panel', 'panel-' . $variant]]) ?>>
        <?php if ($filled($section->title) || $filled($section->options) || $filled($section->header)): ?>
            <header <?= new HtmlAttributes([
                'class' => ['rex-section__header', 'panel-heading', 'collapsed' => $section->collapsible && $section->collapsed],
                'data-toggle' => null === $collapseId ? null : 'collapse',
                'data-target' => null === $collapseId ? null : '#' . $collapseId,
            ]) ?>>
                <?php if ($filled($section->options)): ?>
                    <div class="rex-section__options rex-panel-options"><?= Html::from($section->options) ?></div>
                <?php endif ?>
                <?php if ($filled($section->title)): ?>
                    <div class="rex-section__title panel-title"><?= Html::from($section->title) ?></div>
                <?php endif ?>
                <?= $filled($section->header) ? Html::from($section->header) : '' ?>
            </header>
        <?php endif ?>
        <?php if (null !== $collapseId): ?>
            <div id="<?= $collapseId ?>" class="rex-section__collapse panel-collapse collapse<?= $section->collapsed ? '' : ' in' ?>">
        <?php endif ?>
        <?php if ($filled($section->body)): ?>
            <div class="rex-section__body panel-body"><?= Html::from($section->body) ?></div>
        <?php endif ?>
        <?= $filled($section->content) ? Html::from($section->content) : '' ?>
        <?php if ($filled($section->footer)): ?>
            <footer class="rex-section__footer panel-footer"><?= Html::from($section->footer) ?></footer>
        <?php endif ?>
        <?php if (null !== $collapseId): ?>
            </div>
        <?php endif ?>
    </section>
<?php };
