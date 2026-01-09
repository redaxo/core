<?php
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
    <?php if (isset($this->headline)): ?>
    <h4 class="rex-nav-main-title"><?= $this->headline['title'] ?></h4>
    <?php endif ?>
    <ul class="rex-nav-main-list nav nav-pills nav-stacked">
        <?php foreach ($this->items as $item):

            if (isset($item['active']) && $item['active']):
                $item['itemAttr']['class'][] = 'active';
            endif;

            $icon = '';
            if (isset($item['icon']) && '' != $item['icon']):
                if (isset($item['itemAttr']['class'])) {
                    if (is_array($item['itemAttr']['class'])) {
                        $item['itemAttr']['class'] = array_merge($item['itemAttr']['class'], ['rex-has-icon']);
                    } else {
                        $item['itemAttr']['class'] = [$item['itemAttr']['class'], 'rex-has-icon'];
                    }
                } else {
                    $item['itemAttr']['class'] = ['rex-has-icon'];
                }
                $icon = '<i class="' . trim($item['icon']) . '"></i> ';
            endif;

            $itemAttr = isset($item['itemAttr']) ? Str::buildAttributes($item['itemAttr']) : '';
            $linkAttr = isset($item['linkAttr']) ? Str::buildAttributes($item['linkAttr']) : '';
        ?>

        <li<?= $itemAttr ?>><a href="<?= escape($item['href']) ?>"<?= $linkAttr ?>><?= $icon . $item['title'] ?></a></li>
        <?php endforeach ?>
    </ul>
