<?php

use Redaxo\Core\View\Component\Dropdown;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;

return static function (Dropdown $dropdown): void {
    $filled = static fn (string|Renderable $value): bool => $value instanceof Html ? '' !== (string) $value : $value instanceof Renderable || '' !== $value;
    $toggle = $filled($dropdown->prefix) ? (string) Html::from($dropdown->prefix) : '';
    if ($filled($dropdown->label)) {
        $toggle .= ' <b>' . (string) Html::from($dropdown->label) . '</b>';
    } ?>
    <?php if ($dropdown->wrapped): ?>
        <div <?= $dropdown->attributes->with(['class' => ['dropdown', 'btn-block' => $dropdown->block]]) ?>>
    <?php endif ?>
    <?php if ($dropdown->link): ?>
        <a <?= new HtmlAttributes(['class' => ['dropdown-toggle'], 'href' => '#', 'role' => 'button', 'data-toggle' => 'dropdown', 'disabled' => $dropdown->disabled]) ?>><?= $toggle ?> <span class="caret"></span></a>
    <?php else: ?>
        <button <?= new HtmlAttributes(['class' => ['btn', 'btn-default', 'btn-block' => $dropdown->block, 'dropdown-toggle'], 'type' => 'button', 'data-toggle' => 'dropdown', 'disabled' => $dropdown->disabled]) ?>><?= $toggle ?> <span class="caret"></span></button>
    <?php endif ?>
    <ul <?= new HtmlAttributes(['class' => ['dropdown-menu', 'dropdown-menu-right' => $dropdown->alignEnd, 'btn-block' => $dropdown->block], 'role' => 'menu']) ?>>
        <?php if ($filled($dropdown->header)): ?>
            <li class="dropdown-header"><?= Html::from($dropdown->header) ?></li>
        <?php endif ?>
        <?php foreach ($dropdown->items as $item): ?>
            <li <?= ($item['attributes'] ?? new HtmlAttributes())->with(['class' => ['active' => $item['active'] ?? false]]) ?>>
                <?php if (isset($item['href']) && '' !== $item['href']): ?>
                    <a <?= new HtmlAttributes(['href' => $item['href']]) ?>><?= Html::from($item['title']) ?></a>
                <?php else: ?>
                    <?= Html::from($item['title']) ?>
                <?php endif ?>
            </li>
        <?php endforeach ?>
        <?php if ($filled($dropdown->footer)): ?>
            <li class="divider"></li>
            <li><?= Html::from($dropdown->footer) ?></li>
        <?php endif ?>
    </ul>
    <?php if ($dropdown->wrapped): ?>
        </div>
    <?php endif ?>
<?php };
