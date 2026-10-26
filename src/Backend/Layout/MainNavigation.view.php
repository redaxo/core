<?php

use Redaxo\Core\Backend\Layout\MainNavigation;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;

use function Redaxo\Core\View\escape;

return static function (MainNavigation $navigation): void { ?>
    <nav id="rex-js-nav-main" class="rex-sidebar">
        <div class="rex-sidebar__inner">
            <?php foreach ($navigation->blocks as $block): ?>
                <h4 class="rex-sidebar__title"><?= $block['headline']['title'] ?></h4>
                <ul class="rex-sidebar__list">
                    <?php foreach ($block['navigation'] as $item):
                        /** @var array<string, int|list<string>|string> $itemAttr */
                        $itemAttr = $item['itemAttr'] ?? [];
                        /** @var array<string, int|list<string>|string> $linkAttr */
                        $linkAttr = $item['linkAttr'] ?? [];
                        if ($item['active'] ?? false) {
                            $class = $itemAttr['class'] ?? [];
                            $itemAttr['class'] = [...(is_array($class) ? $class : [(string) $class]), 'is-active'];
                        }
                        $icon = trim(Type::string($item['icon'] ?? '')) ?>
                        <li<?= Str::buildAttributes($itemAttr) ?>>
                            <a href="<?= escape(Type::string($item['href'])) ?>"<?= Str::buildAttributes($linkAttr) ?>>
                                <?php if ('' !== $icon): ?>
                                    <i class="rex-sidebar__icon <?= escape($icon) ?>"></i>
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
