<?php

/**
 * @package redaxo\core\backend
 */
class rex_be_controller
{
    /** @var string */
    private static $page;

    /** @var list<string> */
    private static array $pageParts = [];

    private static ?rex_be_page $pageObject = null;

    /** @var array<string, rex_be_page> */
    private static array $pages = [];

    /**
     * @param string $page
     * @return void
     */
    public static function setCurrentPage($page)
    {
        self::$page = trim($page, '/ ');
        self::$pageParts = explode('/', self::$page);
        self::$pageObject = null;
    }

    /** @return string */
    public static function getCurrentPage()
    {
        return self::$page;
    }

    /**
     * @template T of positive-int|null
     * @param T $part Part index, beginning with 1. If $part is null, an array of all current parts will be returned
     * @param string|null $default Default value
     * @return (T is null ? list<string> : string|null)
     */
    public static function getCurrentPagePart($part = null, $default = null)
    {
        if (null === $part) {
            return self::$pageParts;
        }
        --$part;
        return self::$pageParts[$part] ?? $default;
    }

    /** @return rex_be_page|null */
    public static function getCurrentPageObject()
    {
        if (!self::$pageObject) {
            self::$pageObject = self::getPageObject(self::getCurrentPage());
        }
        return self::$pageObject;
    }

    public static function requireCurrentPageObject(): rex_be_page
    {
        return rex_type::notNull(self::getCurrentPageObject());
    }

    /**
     * @param string|list<string> $page
     *
     * @return rex_be_page|null
     */
    public static function getPageObject($page)
    {
        if (!is_array($page)) {
            $page = explode('/', $page);
        }
        if (!isset($page[0]) || !isset(self::$pages[$page[0]])) {
            return null;
        }
        $obj = self::$pages[$page[0]];
        for ($i = 1, $count = count($page); $i < $count; ++$i) {
            if ($new = $obj->getSubpage($page[$i])) {
                $obj = $new;
            } else {
                return null;
            }
        }
        return $obj;
    }

    /** @return array<string, rex_be_page> */
    public static function getPages()
    {
        return self::$pages;
    }

    /**
     * @param array<string, rex_be_page> $pages
     * @return void
     */
    public static function setPages(array $pages)
    {
        self::$pages = $pages;
    }

    /** @return string */
    public static function getPageTitle()
    {
        $parts = [];

        $activePageObj = self::requireCurrentPageObject();
        if ($activePageObj->getTitle()) {
            $parts[] = $activePageObj->getTitle();
        }
        if (rex::getServerName()) {
            $parts[] = rex::getServerName();
        }
        $parts[] = 'REDAXO CMS';

        return implode(' · ', $parts);
    }

    /** @return rex_be_page */
    public static function getSetupPage()
    {
        $page = new rex_be_page('setup', rex_i18n::msg('setup'));
        $page->setPath(rex_path::core('pages/setup.php'));
        return $page;
    }

    /** @return rex_be_page */
    public static function getLoginPage()
    {
        $page = new rex_be_page('login', 'Login');
        $page->setPath(rex_path::core('pages/login.php'));
        $page->setHasNavigation(false);
        return $page;
    }

    /** @return void */
    public static function appendLoggedInPages()
    {
        self::$pages['profile'] = (new rex_be_page('profile', rex_i18n::msg('profile')))
            ->setPath(rex_path::core('pages/profile.php'))
            ->setPjax();

        self::$pages['credits'] = (new rex_be_page('credits', rex_i18n::msg('credits')))
            ->setPath(rex_path::core('pages/credits.php'));

        $logsPage = (new rex_be_page('log', rex_i18n::msg('logfiles')))->setSubPath(rex_path::core('pages/system.log.php'));
        $logsPage->addSubpage((new rex_be_page('redaxo', rex_i18n::msg('syslog_redaxo')))->setSubPath(rex_path::core('pages/system.log.redaxo.php')));
        if ('' != ini_get('error_log') && @is_readable(ini_get('error_log'))) {
            $logsPage->addSubpage((new rex_be_page('php', rex_i18n::msg('syslog_phperrors')))->setSubPath(rex_path::core('pages/system.log.external.php')));
        }

        if ('system' === self::getCurrentPagePart(1) && 'log' === self::getCurrentPagePart(2)) {
            $slowQueryLogPath = rex_sql_util::slowQueryLogPath();
            if (null !== $slowQueryLogPath && @is_readable($slowQueryLogPath)) {
                $logsPage->addSubpage((new rex_be_page('slow-queries', rex_i18n::msg('syslog_slowqueries')))->setSubPath(rex_path::core('pages/system.log.slow-queries.php')));
            }
        }

        self::$pages['system'] = (new rex_be_page_main('system', 'system', rex_i18n::msg('system')))
            ->setPath(rex_path::core('pages/system.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(80)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-system')
            ->addSubpage((new rex_be_page('settings', rex_i18n::msg('main_preferences')))->setSubPath(rex_path::core('pages/system.settings.php')))
            ->addSubpage((new rex_be_page('lang', rex_i18n::msg('languages')))->setSubPath(rex_path::core('pages/system.clangs.php')))
            ->addSubpage($logsPage)
            ->addSubpage((new rex_be_page('report', rex_i18n::msg('system_report')))
                ->addSubpage((new rex_be_page('html', rex_i18n::msg('system_report')))->setSubPath(rex_path::core('pages/system.report.html.php')))
                ->addSubpage((new rex_be_page('markdown', rex_i18n::msg('system_report_markdown')))->setSubPath(rex_path::core('pages/system.report.markdown.php'))),
            )
            ->addSubpage((new rex_be_page('phpinfo', 'phpinfo'))
                ->setHidden(true)
                ->setHasLayout(false)
                ->setPath(rex_path::core('pages/system.phpinfo.php')),
            );

        if (rex::isLiveMode()) {
            return;
        }

        self::$pages['packages'] = (new rex_be_page_main('system', 'packages', rex_i18n::msg('addons')))
            ->setPath(rex_path::core('pages/packages.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(60)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-package-addon');
    }

    /** @return void */
    public static function appendPackagePages()
    {
        $insertPages = [];
        $addons = rex::isSafeMode() ? rex_addon::getSetupAddons() : rex_addon::getAvailableAddons();
        foreach ($addons as $addon) {
            $mainPage = self::pageCreate($addon->getProperty('page'), $addon, true);

            if (is_array($pages = $addon->getProperty('pages'))) {
                foreach ($pages as $key => $page) {
                    if (str_contains($key, '/')) {
                        $insertPages[$key] = [$addon, $page];
                    } else {
                        self::pageCreate($page, $addon, false, $mainPage, $key, true);
                    }
                }
            }

            // handle plugins
            $plugins = rex::isSafeMode() ? $addon->getSystemPlugins() : $addon->getAvailablePlugins();
            foreach ($plugins as $plugin) {
                self::pageCreate($plugin->getProperty('page'), $plugin, false, $mainPage);

                if (is_array($pages = $plugin->getProperty('pages'))) {
                    foreach ($pages as $key => $page) {
                        if (str_contains($key, '/')) {
                            $insertPages[$key] = [$plugin, $page];
                        } else {
                            self::pageCreate($page, $plugin, false, $mainPage, $key, true);
                        }
                    }
                }
            }
        }
        foreach ($insertPages as $key => $packagePage) {
            [$package, $page] = $packagePage;
            $key = explode('/', $key);
            if (!isset(self::$pages[$key[0]])) {
                continue;
            }
            $parentPage = self::$pages[$key[0]];
            for ($i = 1, $count = count($key) - 1; $i < $count && $parentPage; ++$i) {
                $parentPage = $parentPage->getSubpage($key[$i]);
            }
            if ($parentPage) {
                self::pageCreate($page, $package, false, $parentPage, $key[$i], strtr($parentPage->getFullKey(), '/', '.') . '.' . $key[$i] . '.');
            }
        }
    }

    /**
     * @param rex_be_page|array $page
     * @param bool $createMainPage
     * @param string $pageKey
     * @param bool|string $prefix
     *
     * @return rex_be_page|null
     */
    private static function pageCreate($page, rex_package $package, $createMainPage, ?rex_be_page $parentPage = null, $pageKey = null, $prefix = false)
    {
        if (is_array($page) && isset($page['title']) && (false !== ($page['live_mode'] ?? null) || !rex::isLiveMode())) {
            $pageArray = $page;
            $pageKey = $pageKey ?: $package->getName();
            if ($createMainPage || isset($pageArray['main']) && $pageArray['main']) {
                $page = new rex_be_page_main('addons', $pageKey, $pageArray['title']);
            } else {
                $page = new rex_be_page($pageKey, $pageArray['title']);
            }
            self::pageAddProperties($page, $pageArray, $package);
        }

        if ($page instanceof rex_be_page) {
            if (!is_string($prefix)) {
                $prefix = $prefix ? $page->getKey() . '.' : '';
            }
            if ($page instanceof rex_be_page_main) {
                if (!$page->hasPath()) {
                    $page->setPath($package->getPath('pages/' . ($prefix ?: 'index.') . 'php'));
                }
                self::$pages[$page->getKey()] = $page;
            } else {
                if (!$page->hasSubPath()) {
                    $page->setSubPath($package->getPath('pages/' . ($prefix ?: 'index.') . 'php'));
                }
                if ($parentPage) {
                    $parentPage->addSubpage($page);
                }
            }
            self::pageSetSubPaths($page, $package, $prefix);
            return $page;
        }
        return null;
    }

    /**
     * @param string $prefix
     * @return void
     */
    private static function pageSetSubPaths(rex_be_page $page, rex_package $package, $prefix = '')
    {
        foreach ($page->getSubpages() as $subpage) {
            if (!$subpage->hasSubPath()) {
                $subpage->setSubPath($package->getPath('pages/' . $prefix . $subpage->getKey() . '.php'));
            }
            self::pageSetSubPaths($subpage, $package, $prefix . $subpage->getKey() . '.');
        }
    }

    /** @return void */
    private static function pageAddProperties(rex_be_page $page, array $properties, rex_package $package)
    {
        foreach ($properties as $key => $value) {
            switch (strtolower($key)) {
                case 'subpages':
                    if (is_array($value)) {
                        foreach ($value as $pageKey => $subProperties) {
                            if (isset($subProperties['title']) && (false !== ($subProperties['live_mode'] ?? null) || !rex::isLiveMode())) {
                                $subpage = new rex_be_page($pageKey, $subProperties['title']);
                                $page->addSubpage($subpage);
                                self::pageAddProperties($subpage, $subProperties, $package);
                            }
                        }
                    }
                    break;

                case 'itemattr':
                case 'linkattr':
                    $setter = [$page, 'set' . ucfirst($key)];
                    foreach ($value as $k => $v) {
                        call_user_func($setter, $k, $v);
                    }
                    break;

                case 'perm':
                    $page->setRequiredPermissions($value);
                    break;

                case 'path':
                case 'subpath':
                    if (is_file($path = $package->getPath($value))) {
                        $value = $path;
                    }
                    // no break
                default:
                    $adder = [$page, 'add' . ucfirst($key)];
                    if (is_callable($adder)) {
                        foreach ((array) $value as $v) {
                            call_user_func($adder, $v);
                        }
                        break;
                    }
                    $setter = [$page, 'set' . ucfirst($key)];
                    if (is_callable($setter)) {
                        call_user_func($setter, $value);
                    }
            }
        }
    }

    /** @return void */
    public static function checkPagePermissions(rex_user $user)
    {
        $check = static function (rex_be_page $page) use (&$check, $user) {
            if (!$page->checkPermission($user)) {
                return false;
            }

            $subpages = $page->getSubpages();
            foreach ($subpages as $key => $subpage) {
                if (!$check($subpage)) {
                    unset($subpages[$key]);
                }
            }
            $page->setSubpages($subpages);

            return true;
        };

        foreach (self::$pages as $key => $page) {
            if (!$check($page)) {
                unset(self::$pages[$key]);
            }
        }
        self::$pageObject = null;

        $page = self::getCurrentPageObject();
        // --- page pruefen und benoetigte rechte checken
        if (!$page) {
            // --- fallback zur user startpage -> rechte checken
            $page = self::getPageObject($user->getStartPage());
            if (!$page) {
                // --- fallback zur system startpage -> rechte checken
                $page = self::getPageObject(rex::getProperty('start_page'));
                if (!$page) {
                    // --- fallback zur profile page
                    $page = rex_type::notNull(self::getPageObject('profile'));
                }
            }
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendRedirect($page->getHref());
        }
        if ($page !== $leaf = $page->getFirstSubpagesLeaf()) {
            rex_response::setStatus(rex_response::HTTP_MOVED_PERMANENTLY);
            $url = $leaf->hasHref() ? $leaf->getHref() : rex_context::fromGet()->getUrl(['page' => $leaf->getFullKey()], false);
            rex_response::sendRedirect($url);
        }
    }

    /**
     * Includes the current page. A page may be provided by the core, an addon or plugin.
     * @return void
     */
    public static function includeCurrentPage()
    {
        $currentPage = self::requireCurrentPageObject();

        if (rex_request::isPJAXRequest() && !rex_request::isPJAXContainer('#rex-js-page-container')) {
            // non-core pjax containers should not have a layout.
            // they render their whole response on their own
            $currentPage->setHasLayout(false);
        }

        rex_timer::measure('Layout: top.php', function () {
            require rex_path::core('layout/top.php');
        });

        self::includePath(rex_type::string($currentPage->getPath()));

        rex_timer::measure('Layout: bottom.php', function () {
            require rex_path::core('layout/bottom.php');
        });
    }

    /**
     * Includes the sub-path of current page.
     *
     * @return mixed
     */
    public static function includeCurrentPageSubPath(array $context = [])
    {
        $page = self::requireCurrentPageObject();
        $path = $page->getSubPath();
        if (null === $path) {
            throw new rex_exception(sprintf(
                $page instanceof rex_be_page_main
                    ? 'Current page "%s" is a main page and therefore has no sub-path.'
                    : 'Current page "%s" does not have a sub-path.',
                $page->getFullKey(),
            ));
        }

        if ('.md' !== strtolower(substr($path, -3))) {
            return self::includePath($path, $context);
        }

        $languagePath = substr($path, 0, -3) . '.' . rex_i18n::getLanguage() . '.md';
        if (is_readable($languagePath)) {
            $path = $languagePath;
        }

        [$toc, $content] = rex_markdown::factory()->parseWithToc(rex_file::require($path), 2, 3, [
            rex_markdown::SOFT_LINE_BREAKS => false,
            rex_markdown::HIGHLIGHT_PHP => true,
        ]);
        $fragment = new rex_fragment();
        $fragment->setVar('content', $content, false);
        $fragment->setVar('toc', $toc, false);
        $content = $fragment->parse('core/page/docs.php');

        $fragment = new rex_fragment();
        $fragment->setVar('title', $page->getTitle(), false);
        $fragment->setVar('body', $content, false);
        echo $fragment->parse('core/page/section.php');

        return null;
    }

    /**
     * Includes a path in correct package context.
     *
     * @param string $path
     *
     * @return mixed
     */
    private static function includePath($path, array $context = [])
    {
        return rex_timer::measure('Page: ' . rex_path::relative($path, rex_path::src()), function () use ($path, $context) {
            $pattern = '@' . preg_quote(rex_path::src('addons/'), '@') . '([^/\\\]+)(?:[/\\\]plugins[/\\\]([^/\\\]+))?@';

            if (!preg_match($pattern, $path, $matches)) {
                $__context = $context;
                $__path = $path;

                unset($context, $path, $pattern, $matches);

                extract($__context, EXTR_SKIP);

                return include $__path;
            }

            $package = rex_addon::get($matches[1]);
            if (isset($matches[2])) {
                $package = $package->getPlugin($matches[2]);
            }
            return $package->includeFile(str_replace($package->getPath(), '', $path), $context);
        });
    }
}
