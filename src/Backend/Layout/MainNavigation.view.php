<?php

use Redaxo\Core\Backend\Layout\MainNavigation;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

use function Redaxo\Core\View\escape;

return static function (MainNavigation $navigation): void { ?>
    <nav id="rex-js-nav-main" class="rex-sidebar" aria-label="<?= I18n::msg('navigation_main_label') ?>">
        <div class="rex-sidebar-inner">
            <?php foreach ($navigation->blocks as $block): ?>
                <h2 class="rex-sidebar-title"><?= $block['headline']['title'] ?></h2>
                <ul class="rex-sidebar-list">
                    <?php foreach ($block['navigation'] as $item):
                        /** @var array<string, int|list<string>|string> $itemAttr */
                        $itemAttr = $item['itemAttr'] ?? [];
                        /** @var array<string, int|list<string>|string> $linkAttr */
                        $linkAttr = $item['linkAttr'] ?? [];
                        if ($item['active'] ?? false) {
                            $class = $itemAttr['class'] ?? [];
                            $itemAttr['class'] = [...(is_array($class) ? $class : [(string) $class]), 'is-active'];
                            $linkAttr['aria-current'] = 'page';
                        }
                        $icon = trim(Type::string($item['icon'] ?? '')) ?>
                        <li<?= Str::buildAttributes($itemAttr) ?>>
                            <a href="<?= escape(Type::string($item['href'])) ?>"<?= Str::buildAttributes($linkAttr) ?>>
                                <?php if ('' !== $icon): ?>
                                    <i class="rex-sidebar-icon <?= escape($icon) ?>" aria-hidden="true"></i>
                                <?php endif ?>
                                <?= Type::string($item['title']) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
        </div>
    </nav>
    <div id="rex-js-nav-main-backdrop" class="rex-sidebar-backdrop"></div>
<?php };
