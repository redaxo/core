<?php

use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

return static function (Section $section): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value;
    $variant = $section->variant->value;
    $collapseId = $section->collapsible ? 'rex-section-' . bin2hex(random_bytes(4)) : null;
    ?>
    <section <?= $section->attributes->with(['class' => ['rex-section'], 'data-variant' => SectionVariant::Default === $section->variant ? null : $variant]) ?>>
        <?php if ($filled($section->title) || $filled($section->options) || $filled($section->header)): ?>
            <header class="rex-section-header">
                <?php if ($filled($section->options)): ?>
                    <div class="rex-section-options"><?= Html::from($section->options) ?></div>
                <?php endif ?>
                <?php if ($filled($section->title)): ?>
                    <h<?= $section->headingLevel ?> class="rex-section-title">
                        <?php if (null === $collapseId): ?>
                            <?= Html::from($section->title) ?>
                        <?php else: ?>
                            <button <?= new HtmlAttributes([
                                'type' => 'button',
                                'class' => ['rex-section-toggle', 'collapsed' => $section->collapsed],
                                'data-toggle' => 'collapse',
                                'data-target' => '#' . $collapseId,
                                'aria-controls' => $collapseId,
                                'aria-expanded' => $section->collapsed ? 'false' : 'true',
                            ]) ?>><?= Html::from($section->title) ?></button>
                        <?php endif ?>
                    </h<?= $section->headingLevel ?>>
                <?php endif ?>
                <?= $filled($section->header) ? Html::from($section->header) : '' ?>
            </header>
        <?php endif ?>
        <?php if (null !== $collapseId): ?>
            <div id="<?= $collapseId ?>" class="rex-section-collapse collapse<?= $section->collapsed ? '' : ' in' ?>">
        <?php endif ?>
        <?php if ($filled($section->body)): ?>
            <div class="rex-section-body"><?= Html::from($section->body) ?></div>
        <?php endif ?>
        <?= $filled($section->content) ? Html::from($section->content) : '' ?>
        <?php if ($filled($section->footer)): ?>
            <footer class="rex-section-footer"><?= Html::from($section->footer) ?></footer>
        <?php endif ?>
        <?php if (null !== $collapseId): ?>
            </div>
        <?php endif ?>
    </section>
<?php };
