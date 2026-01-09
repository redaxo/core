<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Markdown;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Fragment;

use function call_user_func;
use function count;
use function ini_get;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

use const EXTR_SKIP;

final class Controller
{
    private static ?string $page = null;

    /** @var list<string> */
    private static array $pageParts = [];

    private static ?Page $pageObject = null;

    /** @var array<string, Page> */
    private static array $pages = [];

    private function __construct() {}

    public static function setCurrentPage(string $page): void
    {
        self::$page = trim($page, '/ ');
        self::$pageParts = explode('/', self::$page);
        self::$pageObject = null;
    }

    public static function getCurrentPage(): string
    {
        if (null === self::$page) {
            throw new LogicException('Calling getCurrentPage before the backend page has been set is not allowed.');
        }

        return self::$page;
    }

    /**
     * @template T of positive-int|null
     * @param T $part Part index, beginning with 1. If $part is null, an array of all current parts will be returned
     * @param string|null $default Default value
     * @return (T is null ? list<string> : string|null)
     */
    public static function getCurrentPagePart(?int $part = null, ?string $default = null): string|array|null
    {
        if (null === $part) {
            return self::$pageParts;
        }
        --$part;
        return self::$pageParts[$part] ?? $default;
    }

    public static function getCurrentPageObject(): ?Page
    {
        if (!self::$pageObject) {
            self::$pageObject = self::getPageObject(self::getCurrentPage());
        }
        return self::$pageObject;
    }

    public static function requireCurrentPageObject(): Page
    {
        return Type::notNull(self::getCurrentPageObject());
    }

    /**
     * @param string|list<string> $page
     */
    public static function getPageObject(string|array $page): ?Page
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

    /**
     * @return array<string, Page>
     */
    public static function getPages(): array
    {
        return self::$pages;
    }

    /**
     * @param array<string, Page> $pages
     */
    public static function setPages(array $pages): void
    {
        self::$pages = $pages;
    }

    public static function getPageTitle(): string
    {
        $parts = [];

        $activePageObj = self::requireCurrentPageObject();
        if ($activePageObj->getTitle()) {
            $parts[] = $activePageObj->getTitle();
        }
        if (Core::getServerName()) {
            $parts[] = Core::getServerName();
        }
        $parts[] = 'REDAXO CMS';

        return implode(' · ', $parts);
    }

    public static function getSetupPage(): Page
    {
        $page = new Page('setup', I18n::msg('setup'));
        $page->setPath(Path::core('pages/setup.php'));
        return $page;
    }

    public static function getLoginPage(): Page
    {
        $page = new Page('login', 'Login');
        $page->setPath(Path::core('pages/login.php'));
        $page->setHasNavigation(false);
        return $page;
    }

    public static function appendLoggedInPages(): void
    {
        self::$pages['profile'] = new Page('profile', I18n::msg('profile'))
            ->setPath(Path::core('pages/profile.php'))
            ->setPjax();

        self::$pages['credits'] = new Page('credits', I18n::msg('credits'))
            ->setPath(Path::core('pages/credits.php'));

        $logsPage = new Page('log', I18n::msg('logfiles'))->setSubPath(Path::core('pages/system.log.php'));
        $logsPage->addSubpage(new Page('redaxo', I18n::msg('syslog_redaxo'))->setSubPath(Path::core('pages/system.log.redaxo.php')));
        if ('' != ini_get('error_log') && @is_readable(ini_get('error_log'))) {
            $logsPage->addSubpage(new Page('php', I18n::msg('syslog_phperrors'))->setSubPath(Path::core('pages/system.log.external.php')));
        }
        $logsPage->addSubpage(new Page('phpmailer', I18n::msg('phpmailer_title'))->setSubPath(Path::core('pages/phpmailer.log.php')));

        if ('system' === self::getCurrentPagePart(1) && 'log' === self::getCurrentPagePart(2)) {
            $slowQueryLogPath = Util::slowQueryLogPath();
            if (null !== $slowQueryLogPath && @is_readable($slowQueryLogPath)) {
                $logsPage->addSubpage(new Page('slow-queries', I18n::msg('syslog_slowqueries'))->setSubPath(Path::core('pages/system.log.slow-queries.php')));
            }
        }

        $logsPage->addSubpage(new Page('cronjob', I18n::msg('cronjob_title'))->setSubPath(Path::core('pages/system.log.cronjob.php')));

        $beStylePage = (new Page('be_style', I18n::msg('be_style')));
        $beStylePage
            ->addSubpage(new Page('customizer', I18n::msg('customizer'))->setSubPath(Path::core('pages/system.be_style.customizer.php')))
            ->addSubpage(new Page('icons', I18n::msg('be_style_icons'))->setSubPath(Path::core('pages/system.be_style.icons.php')))
            ->addSubpage(new Page('help', I18n::msg('be_style_help'))->setSubPath(Path::core('pages/system.be_style.README.md')));

        self::$pages['structure'] = new MainPage('system', 'structure', I18n::msg('structure'))
            ->setPath(Path::core('pages/structure.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPrio(10)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-open-category')
        ;
        self::$pages['modules'] = new MainPage('system', 'modules', I18n::msg('modules'))
            ->setPath(Path::core('pages/structure.modules.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(40)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-module')
            ->addSubpage(new Page('modules', I18n::msg('modules'))->setSubPath(Path::core('pages/structure.modules.modules.php')))
            ->addSubpage(new Page('actions', I18n::msg('actions'))->setSubPath(Path::core('pages/structure.modules.actions.php')))
        ;
        self::$pages['templates'] = new MainPage('system', 'templates', I18n::msg('templates'))
            ->setPath(Path::core('pages/structure.templates.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(30)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-template')
        ;
        self::$pages['content'] = new MainPage('system', 'content', I18n::msg('content'))
            ->setPath(Path::core('pages/structure.content.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPjax(false)
            ->setHidden()
            ->addSubpage(new Page('edit', I18n::msg('edit_mode'))
                ->setSubPath(Path::core('pages/structure.content.edit.php'))
                ->setIcon('rex-icon rex-icon-editmode')
                ->setItemAttr('left', 'true'),
            )
            ->addSubpage(new Page('functions', I18n::msg('metafuncs'))
                ->setSubPath(Path::core('pages/structure.content.functions.php'))
                ->setIcon('rex-icon rex-icon-metafuncs'),
            )
        ;
        self::$pages['linkmap'] = new MainPage('system', 'linkmap', I18n::msg('linkmap'))
            ->setPath(Path::core('pages/structure.linkmap.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPjax()
            ->setPopup(true)
            ->setHidden()
        ;

        self::$pages['system'] = new MainPage('system', 'system', I18n::msg('system'))
            ->setPath(Path::core('pages/system.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(100)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-system')
            ->addSubpage(new Page('settings', I18n::msg('main_preferences'))->setSubPath(Path::core('pages/system.settings.php')))
            ->addSubpage(new Page('lang', I18n::msg('languages'))->setSubPath(Path::core('pages/system.clangs.php')))
            ->addSubpage($logsPage)
            ->addSubpage(
                new Page('report', I18n::msg('system_report'))
                ->addSubpage(new Page('html', I18n::msg('system_report'))->setSubPath(Path::core('pages/system.report.html.php')))
                ->addSubpage(new Page('markdown', I18n::msg('system_report_markdown'))->setSubPath(Path::core('pages/system.report.markdown.php'))),
            )
            ->addSubpage($beStylePage)
            ->addSubpage(new Page('phpinfo', 'phpinfo')
                ->setHidden(true)
                ->setHasLayout(false)
                ->setPath(Path::core('pages/system.phpinfo.php')),
            )
        ;

        if (Core::getConfig('article_history', false)) {
            self::$pages['content']->addSubpage(new Page('history', '')
                ->setRequiredPermissions('history[article_rollback]')
                ->setIcon('fa fa-history')
                ->setHref('#')
                ->setItemAttr('left', 'true')
                ->setLinkAttr('data-history-layer', 'open'),
            );
            self::$pages['system']->addSubpage(new Page('history', I18n::msg('structure_history'))->setSubPath(Path::core('pages/structure.system.history.php')));
        }

        self::$pages['users'] = new MainPage('system', 'users', I18n::msg('users'))
            ->setPath(Path::core('pages/users.php'))
            ->setRequiredPermissions('users[]')
            ->setPrio(50)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-user')
            ->addSubpage(
                new Page('users', I18n::msg('users'))
                    ->setSubPath(Path::core('pages/users.users.php')),
            )
            ->addSubpage(
                new Page('roles', I18n::msg('roles'))
                    ->setSubPath(Path::core('pages/users.roles.php'))
                    ->setRequiredPermissions('isAdmin'),
            )
        ;

        self::$pages['cronjob'] = new MainPage('system', 'cronjob', I18n::msg('cronjob_title'))
            ->setPath(Path::core('pages/cronjob.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(80)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-cronjob')
            ->addSubpage(new Page('cronjobs', I18n::msg('cronjob_title'))->setSubPath(Path::core('pages/cronjob.cronjobs.php')))
            ->addSubpage(new Page('log', I18n::msg('cronjob_log'))->setSubPath(Path::core('pages/cronjob.log.php')))
        ;

        self::$pages['mediapool'] = new MainPage('system', 'mediapool', I18n::msg('mediapool'))
            ->setPath(Path::core('pages/mediapool.php'))
            ->setRequiredPermissions('media/hasMediaPerm')
            ->setPrio(20)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-media')
            ->setPopup('openMediaPool(); return false;')
            ->addSubpage(new Page('media', I18n::msg('pool_file_list'))->setSubPath(Path::core('pages/mediapool.media.php')))
            ->addSubpage(new Page('upload', I18n::msg('pool_file_insert'))->setSubPath(Path::core('pages/mediapool.upload.php')))
            ->addSubpage(new Page('structure', I18n::msg('pool_cat_list'))->setRequiredPermissions('media/hasAll')->setSubPath(Path::core('pages/mediapool.structure.php')))
            ->addSubpage(new Page('sync', I18n::msg('pool_sync_files'))->setRequiredPermissions('media[sync]')->setSubPath(Path::core('pages/mediapool.sync.php')))
        ;

        self::$pages['phpmailer'] = new MainPage('system', 'phpmailer', I18n::msg('phpmailer_title'))
            ->setPath(Path::core('pages/phpmailer.php'))
            ->setRequiredPermissions('phpmailer[]')
            ->setPrio(90)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-envelope' . (Core::getConfig('phpmailer_detour_mode') ? ' text-danger' : ''))
            ->addSubpage(new Page('config', I18n::msg('phpmailer_configuration'))->setSubPath(Path::core('pages/phpmailer.config.php')))
            ->addSubpage(new Page('log', I18n::msg('phpmailer_logging'))->setSubPath(Path::core('pages/phpmailer.log.php')))
            ->addSubpage(new Page('archive', I18n::msg('phpmailer_archive'))->setSubPath(Path::core('pages/phpmailer.archive.php')))
            ->addSubpage(new Page('help', I18n::msg('phpmailer_help'))->setSubPath(Path::core('pages/phpmailer.README.md'))->setItemAttr('class', 'pull-right'))
            ->addSubpage(new Page('checkmail', I18n::msg('phpmailer_checkmail'))->setSubPath(Path::core('pages/phpmailer.checkmail.php'))->setHidden(true))
        ;

        self::$pages['backup'] = $backup = new MainPage('system', 'backup', I18n::msg('backup_title'))
            ->setPath(Path::core('pages/backup.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(110)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-backup')
            ->addSubpage(
                new Page('export', I18n::msg('backup_export'))
                    ->setSubPath(Path::core('pages/backup.export.php'))
                    ->setRequiredPermissions('backup[export]'),
            )
        ;

        if (Core::isLiveMode()) {
            return;
        }

        $backup->addSubpage(new Page('import', I18n::msg('backup_import'))
            ->addSubpage(new Page('upload', I18n::msg('backup_upload'))->setSubPath(Path::core('pages/backup.import.upload.php')))
            ->addSubpage(new Page('server', I18n::msg('backup_load_from_server'))->setSubPath(Path::core('pages/backup.import.server.php'))),
        );

        self::$pages['packages'] = new MainPage('system', 'packages', I18n::msg('addons'))
            ->setPath(Path::core('pages/packages.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(60)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-package-addon');

        self::$pages['media_manager'] = new MainPage('system', 'media_manager', I18n::msg('media_manager'))
            ->setPath(Path::core('pages/media_manager.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(70)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-media')
            ->addSubpage(new Page('types', I18n::msg('media_manager_subpage_types'))->setSubPath(Path::core('pages/media_manager.types.php')))
            ->addSubpage(new Page('settings', I18n::msg('media_manager_subpage_config'))->setSubPath(Path::core('pages/media_manager.settings.php')))
            ->addSubpage(new Page('overview', I18n::msg('media_manager_subpage_desc'))->setSubPath(Path::core('pages/media_manager.README.md')))
            ->addSubpage(new Page('clear_cache', I18n::msg('media_manager_subpage_clear_cache'))
                ->setItemAttr('class', 'pull-right')
                ->setLinkAttr('class', 'btn btn-delete')
                ->setHref(['page' => 'media_manager/types', 'func' => 'clear_cache']),
            )
        ;

        self::$pages['metainfo'] = new MainPage('system', 'metainfo', I18n::msg('metainfo'))
            ->setPath(Path::core('pages/metainfo.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(75)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-metainfo')
            ->addSubpage(new Page('articles', I18n::msg('metainfo_articles')))
            ->addSubpage(new Page('categories', I18n::msg('metainfo_categories')))
            ->addSubpage(new Page('media', I18n::msg('metainfo_media')))
            ->addSubpage(new Page('clangs', I18n::msg('metainfo_clangs')))
            ->addSubpage(new Page('help', I18n::msg('metainfo_help'))->setSubPath(Path::core('pages/metainfo.README.md')))
        ;
    }

    public static function appendPackagePages(): void
    {
        $insertPages = [];
        $addons = Core::isSafeMode() ? Addon::getSetupAddons() : Addon::getAvailableAddons();
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

    private static function pageCreate(Page|array|null $page, Addon $package, bool $createMainPage, ?Page $parentPage = null, ?string $pageKey = null, bool|string $prefix = false): ?Page
    {
        if (is_array($page) && isset($page['title']) && (false !== ($page['live_mode'] ?? null) || !Core::isLiveMode())) {
            $pageArray = $page;
            $pageKey = $pageKey ?: $package->getName();
            if ($createMainPage || isset($pageArray['main']) && $pageArray['main']) {
                $page = new MainPage('addons', $pageKey, $pageArray['title']);
            } else {
                $page = new Page($pageKey, $pageArray['title']);
            }
            self::pageAddProperties($page, $pageArray, $package);
        }

        if ($page instanceof Page) {
            if (!is_string($prefix)) {
                $prefix = $prefix ? $page->getKey() . '.' : '';
            }
            if ($page instanceof MainPage) {
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

    private static function pageSetSubPaths(Page $page, Addon $package, string $prefix = ''): void
    {
        foreach ($page->getSubpages() as $subpage) {
            if (!$subpage->hasSubPath()) {
                $subpage->setSubPath($package->getPath('pages/' . $prefix . $subpage->getKey() . '.php'));
            }
            self::pageSetSubPaths($subpage, $package, $prefix . $subpage->getKey() . '.');
        }
    }

    private static function pageAddProperties(Page $page, array $properties, Addon $package): void
    {
        foreach ($properties as $key => $value) {
            switch (strtolower($key)) {
                case 'subpages':
                    if (is_array($value)) {
                        foreach ($value as $pageKey => $subProperties) {
                            if (isset($subProperties['title']) && (false !== ($subProperties['live_mode'] ?? null) || !Core::isLiveMode())) {
                                $subpage = new Page($pageKey, $subProperties['title']);
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

    public static function checkPagePermissions(User $user): void
    {
        $check = static function (Page $page) use (&$check, $user): bool {
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
            $page = $user->getStartPage();
            $page = $page ? self::getPageObject($page) : null;
            if (!$page) {
                // --- fallback zur system startpage -> rechte checken
                $page = self::getPageObject(Core::getProperty('start_page'));
                if (!$page) {
                    // --- fallback zur profile page
                    $page = Type::notNull(self::getPageObject('profile'));
                }
            }
            Response::setStatus(Response::HTTP_NOT_FOUND);
            Response::sendRedirect($page->getHref());
        }
        if ($page !== $leaf = $page->getFirstSubpagesLeaf()) {
            Response::setStatus(Response::HTTP_MOVED_PERMANENTLY);
            $url = $leaf->hasHref() ? $leaf->getHref() : Context::fromGet()->getUrl(['page' => $leaf->getFullKey()]);
            Response::sendRedirect($url);
        }
    }

    /**
     * Includes the current page. A page may be provided by the core or an addon.
     */
    public static function includeCurrentPage(): void
    {
        $currentPage = self::requireCurrentPageObject();

        if (Request::isPJAXRequest() && !Request::isPJAXContainer('#rex-js-page-container')) {
            // non-core pjax containers should not have a layout.
            // they render their whole response on their own
            $currentPage->setHasLayout(false);
        }

        Timer::measure('Layout: top.php', function () {
            require Path::core('layout/top.php');
        });

        self::includePath(Type::string($currentPage->getPath()));

        Timer::measure('Layout: bottom.php', function () {
            require Path::core('layout/bottom.php');
        });
    }

    /**
     * Includes the sub-path of current page.
     *
     * @param array<literal-string, mixed> $context
     * @return mixed
     */
    public static function includeCurrentPageSubPath(array $context = [])
    {
        $page = self::requireCurrentPageObject();
        $path = $page->getSubPath();
        if (null === $path) {
            throw new LogicException(sprintf(
                $page instanceof MainPage
                    ? 'Current page "%s" is a main page and therefore has no sub-path.'
                    : 'Current page "%s" does not have a sub-path.',
                $page->getFullKey(),
            ));
        }

        if ('.md' !== strtolower(substr($path, -3))) {
            return self::includePath($path, $context);
        }

        $languagePath = substr($path, 0, -3) . '.' . I18n::getLanguage() . '.md';
        if (is_readable($languagePath)) {
            $path = $languagePath;
        }

        [$toc, $content] = Markdown::factory()->parseWithToc(File::require($path), 2, 3, [
            Markdown::SOFT_LINE_BREAKS => false,
            Markdown::HIGHLIGHT_PHP => true,
        ]);
        $fragment = new Fragment();
        $fragment->setVar('content', $content, false);
        $fragment->setVar('toc', $toc, false);
        $content = $fragment->parse('core/page/docs.php');

        $fragment = new Fragment();
        $fragment->setVar('title', $page->getTitle(), false);
        $fragment->setVar('body', $content, false);
        echo $fragment->parse('core/page/section.php');

        return null;
    }

    /**
     * Includes a path in correct package context.
     *
     * @param array<literal-string, mixed> $context
     */
    private static function includePath(string $path, array $context = []): mixed
    {
        return Timer::measure('Page: ' . Path::relative($path, Path::src()), function () use ($path, $context) {
            $pattern = '@' . preg_quote(Path::src('addons/'), '@') . '([^/\\\]+)@';

            if (!preg_match($pattern, $path, $matches)) {
                $__context = $context;
                $__path = $path;

                unset($context, $path, $pattern, $matches);

                extract($__context, EXTR_SKIP);

                return include $__path;
            }

            $package = Addon::get($matches[1]);
            return $package->includeFile(str_replace($package->getPath(), '', $path), $context);
        });
    }
}
