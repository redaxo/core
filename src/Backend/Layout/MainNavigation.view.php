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
                            $linkAttr['aria-current'] = 'page';
                        }
                        // the full title as tooltip, as the narrow navigation may cut the label
                        $linkAttr['title'] ??= strip_tags(Type::string($item['title']));
                        // pages without their own icon get a generic one, so that the narrow navigation stays aligned
                        $icon = trim(Type::string($item['icon'] ?? '')) ?: 'rex-icon rex-icon-package-addon' ?>
                        <li<?= Str::buildAttributes($itemAttr) ?>>
                            <a href="<?= escape(Type::string($item['href'])) ?>"<?= Str::buildAttributes($linkAttr) ?>>
                                <i class="rex-sidebar-icon <?= escape($icon) ?>" aria-hidden="true"></i>
                                <span class="rex-sidebar-label"><?= Type::string($item['title']) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
        </div>
    </nav>
    <div id="rex-js-nav-main-backdrop" class="rex-sidebar-backdrop"></div>
<?php };
