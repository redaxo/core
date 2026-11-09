<?php

use Redaxo\Core\View\Component\Breakpoint;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridColumn;
use Redaxo\Core\View\Component\GridColumnRule;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<section class="rex-main-frame">
    <?php if (isset($this->content) && '' != $this->content && isset($this->sidebar) && '' != $this->sidebar): ?>
    <?= new Grid([
        new GridColumn(Html::raw((string) $this->content), [new GridColumnRule(Breakpoint::Lg, width: 8)], attributes: new HtmlAttributes(['id' => 'rex-js-main-content'])),
        new GridColumn(Html::raw((string) $this->sidebar), [new GridColumnRule(Breakpoint::Lg, width: 4)], attributes: new HtmlAttributes(['id' => 'rex-js-main-sidebar'])),
    ])->render() ?>
    <?php elseif (isset($this->content) && '' != $this->content): ?>
    <div id="rex-js-main-content">
        <?= $this->content ?>
    </div>
    <?php endif ?>
</section>
