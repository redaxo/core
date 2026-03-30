<?php

/**
 * @package redaxo\core\backend
 */
class rex_be_navigation
{
    use rex_factory_trait;

    /** @var array<string, string> */
    private array $headlines = [];

    /** @var array<string, int> */
    private array $prios = [
        'default' => 0,
        'system' => 10,
        'addons' => 20,
    ];

    /** @var array<string, list<rex_be_page>> */
    private array $pages = [];

    /** @return static */
    public static function factory()
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    /** @return void */
    public function addPage(rex_be_page $page)
    {
        $blockName = 'default';
        if ($page instanceof rex_be_page_main) {
            $blockName = $page->getBlock();
        }

        if (!isset($this->pages[$blockName])) {
            $this->pages[$blockName] = [];
        }

        $this->pages[$blockName][] = $page;
    }

    /** @return list<array{navigation: list<array<string, mixed>>, headline: array{title: string}}> */
    public function getNavigation()
    {
        uksort($this->pages, function (string $block1, string $block2) {
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
            if (count($blockPages) > 0 && $blockPages[0] instanceof rex_be_page_main) {
                uasort($blockPages, static function (rex_be_page $a, rex_be_page $b) {
                    $aPrio = $a instanceof rex_be_page_main ? (int) $a->getPrio() : 0;
                    $bPrio = $b instanceof rex_be_page_main ? (int) $b->getPrio() : 0;
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
                $fragment = new rex_fragment();
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
     * @param array<rex_be_page> $blockPages
     *
     * @return list<array<string, mixed>>
     */
    private function _getNavigation(array $blockPages)
    {
        $navigation = [];

        foreach ($blockPages as $page) {
            if ($page->isHidden() || !$page->checkPermission(rex::requireUser())) {
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
            } elseif ($page instanceof rex_be_page_main) {
                $n['icon'] = 'rex-icon rex-icon-package-addon';
            }

            $subpages = $page->getSubpages();
            if (is_array($subpages) && !empty($subpages)) {
                $n['children'] = $this->_getNavigation($subpages);
            }

            $navigation[] = $n;
        }

        return $navigation;
    }

    /** @return void */
    protected function setActiveElements()
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

    /**
     * @param string $block
     * @param string $headline
     * @return void
     */
    public function setHeadline($block, $headline)
    {
        $this->headlines[$block] = $headline;
    }

    /**
     * @param string $block
     *
     * @return string
     */
    public function getHeadline($block)
    {
        if (isset($this->headlines[$block])) {
            return $this->headlines[$block];
        }

        if ('default' != $block) {
            return rex_i18n::msg('navigation_' . $block);
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
