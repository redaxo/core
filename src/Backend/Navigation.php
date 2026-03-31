<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function count;

/**
 * @psalm-consistent-constructor
 */
class Navigation
{
    use FactoryTrait;

    /** @var array<string, string> */
    private array $headlines = [];

    /** @var array<string, int> */
    private array $prios = [
        'default' => 0,
        'system' => 10,
        'addons' => 20,
    ];

    /** @var array<string, list<Page>> */
    private array $pages = [];

    protected function __construct() {}

    public static function factory(): static
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    public function addPage(Page $page): void
    {
        $blockName = 'default';
        if ($page instanceof MainPage) {
            $blockName = $page->getBlock();
        }

        if (!isset($this->pages[$blockName])) {
            $this->pages[$blockName] = [];
        }

        $this->pages[$blockName][] = $page;
    }

    /** @return list<array{navigation: list<array<string, mixed>>, headline: array{title: string}}> */
    public function getNavigation(): array
    {
        uksort($this->pages, function (string $block1, string $block2): int {
            $prio1 = $this->getPrio($block1);
            $prio2 = $this->getPrio($block2);

            if (null === $prio2) {
                return -1;
            }
            if (null === $prio1) {
                return 1;
            }

            return $prio1 <=> $prio2;
        });

        // $this->setActiveElements();
        $return = [];
        foreach ($this->pages as $block => $blockPages) {
            if (count($blockPages) > 0 && $blockPages[0] instanceof MainPage) {
                uasort($blockPages, static function (Page $a, Page $b): int {
                    $aPrio = $a instanceof MainPage ? $a->getPrio() : 0;
                    $bPrio = $b instanceof MainPage ? $b->getPrio() : 0;
                    if ($aPrio === $bPrio || ($aPrio <= 0 && $bPrio <= 0)) {
                        return strnatcasecmp($a->getTitle(), $b->getTitle());
                    }

                    if ($aPrio <= 0) {
                        return 1;
                    }

                    if ($bPrio <= 0) {
                        return -1;
                    }

                    return $aPrio > $bPrio ? 1 : -1;
                });
            }

            $n = $this->_getNavigation($blockPages);
            if (count($n) > 0) {
                $fragment = new Fragment();
                $fragment->setVar('navigation', $n, false);

                $return[] = [
                    'navigation' => $n,
                    'headline' => ['title' => $this->getHeadline($block)],
                ];
            }
        }
        return $return;
    }

    /**
     * @param array<Page> $blockPages
     *
     * @return list<array<string, mixed>>
     */
    private function _getNavigation(array $blockPages): array
    {
        $navigation = [];

        foreach ($blockPages as $page) {
            if ($page->isHidden() || !$page->checkPermission(Core::requireUser())) {
                continue;
            }
            $n = [];
            $n['linkClasses'] = [];
            $n['itemClasses'] = [];
            $n['linkAttr'] = [];
            $n['itemAttr'] = [];

            $n['itemClasses'][] = $page->getItemAttr('class');
            $n['linkClasses'][] = $page->getItemAttr('class');

            foreach ($page->getItemAttr(null) as $name => $value) {
                $n['itemAttr'][$name] = trim($value);
            }

            foreach ($page->getLinkAttr(null) as $name => $value) {
                $n['linkAttr'][$name] = trim($value);
            }

            $n['href'] = $page->getHref();
            $n['title'] = $page->getTitle();
            $n['active'] = $page->isActive();

            if ($page->hasIcon()) {
                $n['icon'] = $page->getIcon();
            } elseif ($page instanceof MainPage) {
                $n['icon'] = 'rex-icon rex-icon-package-addon';
            }

            $subpages = $page->getSubpages();
            if ($subpages) {
                $n['children'] = $this->_getNavigation($subpages);
            }

            $navigation[] = $n;
        }

        return $navigation;
    }

    protected function setActiveElements(): void
    {
        foreach ($this->pages as $blockPages) {
            foreach ($blockPages as $page) {
                // check main pages
                if ($page->isActive()) {
                    $page->addItemClass('active');

                    // check for subpages
                    foreach ($page->getSubpages() as $subpage) {
                        if ($subpage->isActive()) {
                            $subpage->addItemClass('active');
                        }
                    }
                }
            }
        }
    }

    public function setHeadline(string $block, string $headline): void
    {
        $this->headlines[$block] = $headline;
    }

    public function getHeadline(string $block): string
    {
        if (isset($this->headlines[$block])) {
            return $this->headlines[$block];
        }

        if ('default' != $block) {
            return I18n::msg('navigation_' . $block);
        }

        return '';
    }

    public function setPrio(string $block, int $prio): void
    {
        $this->prios[$block] = $prio;
    }

    public function getPrio(string $block): ?int
    {
        return $this->prios[$block] ?? null;
    }
}
