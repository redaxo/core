<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\LogicException;
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

use function count;
use function ini_get;
use function is_array;
use function sprintf;

use const DIRECTORY_SEPARATOR;
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

    /** @param string|list<string> $page */
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

    /** @return array<string, Page> */
    public static function getPages(): array
    {
        return self::$pages;
    }

    /** @param array<string, Page> $pages */
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
        $page->setPath(Path::core('pages/setup/index.php'));
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
            ->setPath(Path::core('pages/profile/index.php'))
            ->setPjax();

        self::$pages['credits'] = new Page('credits', I18n::msg('credits'))
            ->setPath(Path::core('pages/credits.php'));

        $logsPage = new Page('log', I18n::msg('logfiles'))->setSubPath(Path::core('pages/system/log.php'));
        $logsPage->addSubpage(new Page('redaxo', I18n::msg('syslog_redaxo'))->setSubPath(Path::core('pages/system/log.redaxo.php')));
        if ('' != ini_get('error_log') && @is_readable(ini_get('error_log'))) {
            $logsPage->addSubpage(new Page('php', I18n::msg('syslog_phperrors'))->setSubPath(Path::core('pages/system/log.external.php')));
        }
        $logsPage->addSubpage(new Page('phpmailer', I18n::msg('phpmailer_title'))->setSubPath(Path::core('pages/mailer/log.php')));

        if ('system' === self::getCurrentPagePart(1) && 'log' === self::getCurrentPagePart(2)) {
            $slowQueryLogPath = Util::slowQueryLogPath();
            if (null !== $slowQueryLogPath && @is_readable($slowQueryLogPath)) {
                $logsPage->addSubpage(new Page('slow_queries', I18n::msg('syslog_slowqueries'))->setSubPath(Path::core('pages/system/log.slow_queries.php')));
            }
        }

        $logsPage->addSubpage(new Page('cronjob', I18n::msg('cronjob_title'))->setSubPath(Path::core('pages/cronjob/log.php')));

        $beStylePage = (new Page('be_style', I18n::msg('be_style')));
        $beStylePage
            ->addSubpage(new Page('customizer', I18n::msg('customizer'))->setSubPath(Path::core('pages/system/be_style.customizer.php')))
            ->addSubpage(new Page('icons', I18n::msg('be_style_icons'))->setSubPath(Path::core('pages/system/be_style.icons.php')))
            ->addSubpage(new Page('help', I18n::msg('be_style_help'))->setSubPath(Path::core('pages/system/be_style.help.md')));

        self::$pages['structure'] = new MainPage('system', 'structure', I18n::msg('structure'))
            ->setPath(Path::core('pages/structure/index.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPrio(10)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-open-category')
        ;
        self::$pages['content'] = new MainPage('system', 'content', I18n::msg('content'))
            ->setPath(Path::core('pages/structure/content.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPjax(false)
            ->setHidden()
            ->addSubpage(new Page('edit', I18n::msg('edit_mode'))
                ->setSubPath(Path::core('pages/structure/content.edit.php'))
                ->setIcon('rex-icon rex-icon-editmode')
                ->setItemAttr('left', 'true'),
            )
            ->addSubpage(new Page('functions', I18n::msg('metafuncs'))
                ->setSubPath(Path::core('pages/structure/content.functions.php'))
                ->setIcon('rex-icon rex-icon-metafuncs'),
            )
        ;
        self::$pages['linkmap'] = new MainPage('system', 'linkmap', I18n::msg('linkmap'))
            ->setPath(Path::core('pages/structure/linkmap.php'))
            ->setRequiredPermissions('structure/hasStructurePerm')
            ->setPjax()
            ->setPopup(true)
            ->setHidden()
        ;

        self::$pages['system'] = new MainPage('system', 'system', I18n::msg('system'))
            ->setPath(Path::core('pages/system/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(100)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-system')
            ->addSubpage(new Page('settings', I18n::msg('main_preferences'))->setSubPath(Path::core('pages/system/settings.php')))
            ->addSubpage(new Page('lang', I18n::msg('languages'))->setSubPath(Path::core('pages/system/clangs.php')))
            ->addSubpage($logsPage)
            ->addSubpage(
                new Page('report', I18n::msg('system_report'))
                ->addSubpage(new Page('html', I18n::msg('system_report'))->setSubPath(Path::core('pages/system/report.html.php')))
                ->addSubpage(new Page('markdown', I18n::msg('system_report_markdown'))->setSubPath(Path::core('pages/system/report.markdown.php'))),
            )
            ->addSubpage($beStylePage)
            ->addSubpage(new Page('phpinfo', 'phpinfo')
                ->setHidden(true)
                ->setHasLayout(false)
                ->setPath(Path::core('pages/system/phpinfo.php')),
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
            self::$pages['system']->addSubpage(new Page('history', I18n::msg('structure_history'))->setSubPath(Path::core('pages/system/history.php')));
        }

        self::$pages['users'] = new MainPage('system', 'users', I18n::msg('users'))
            ->setPath(Path::core('pages/user/index.php'))
            ->setRequiredPermissions('users[]')
            ->setPrio(50)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-user')
            ->addSubpage(
                new Page('users', I18n::msg('users'))
                    ->setSubPath(Path::core('pages/user/users.php')),
            )
            ->addSubpage(
                new Page('roles', I18n::msg('roles'))
                    ->setSubPath(Path::core('pages/user/roles.php'))
                    ->setRequiredPermissions('isAdmin'),
            )
        ;

        self::$pages['cronjob'] = new MainPage('system', 'cronjob', I18n::msg('cronjob_title'))
            ->setPath(Path::core('pages/cronjob/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(80)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-cronjob')
            ->addSubpage(new Page('cronjobs', I18n::msg('cronjob_title'))->setSubPath(Path::core('pages/cronjob/cronjobs.php')))
            ->addSubpage(new Page('log', I18n::msg('cronjob_log'))->setSubPath(Path::core('pages/cronjob/log.php')))
        ;

        self::$pages['mediapool'] = new MainPage('system', 'mediapool', I18n::msg('mediapool'))
            ->setPath(Path::core('pages/mediapool/index.php'))
            ->setRequiredPermissions('media/hasMediaPerm')
            ->setPrio(20)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-media')
            ->setPopup('openMediaPool(); return false;')
            ->addSubpage(new Page('media', I18n::msg('pool_file_list'))->setSubPath(Path::core('pages/mediapool/media.php')))
            ->addSubpage(new Page('upload', I18n::msg('pool_file_insert'))->setSubPath(Path::core('pages/mediapool/upload.php')))
            ->addSubpage(new Page('structure', I18n::msg('pool_cat_list'))->setRequiredPermissions('media/hasAll')->setSubPath(Path::core('pages/mediapool/structure.php')))
            ->addSubpage(new Page('sync', I18n::msg('pool_sync_files'))->setRequiredPermissions('media[sync]')->setSubPath(Path::core('pages/mediapool/sync.php')))
        ;

        self::$pages['phpmailer'] = new MainPage('system', 'phpmailer', I18n::msg('phpmailer_title'))
            ->setPath(Path::core('pages/mailer/index.php'))
            ->setRequiredPermissions('phpmailer[]')
            ->setPrio(90)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-envelope' . (Core::getConfig('phpmailer_detour_mode') ? ' text-danger' : ''))
            ->addSubpage(new Page('config', I18n::msg('phpmailer_configuration'))->setSubPath(Path::core('pages/mailer/config.php')))
            ->addSubpage(new Page('log', I18n::msg('phpmailer_logging'))->setSubPath(Path::core('pages/mailer/log.php')))
            ->addSubpage(new Page('archive', I18n::msg('phpmailer_archive'))->setSubPath(Path::core('pages/mailer/archive.php')))
            ->addSubpage(new Page('help', I18n::msg('phpmailer_help'))->setSubPath(Path::core('pages/mailer/help.md'))->setItemAttr('class', 'pull-right'))
            ->addSubpage(new Page('checkmail', I18n::msg('phpmailer_checkmail'))->setSubPath(Path::core('pages/mailer/checkmail.php'))->setHidden(true))
        ;

        self::$pages['backup'] = $backup = new MainPage('system', 'backup', I18n::msg('backup_title'))
            ->setPath(Path::core('pages/backup/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(110)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-backup')
            ->addSubpage(
                new Page('export', I18n::msg('backup_export'))
                    ->setSubPath(Path::core('pages/backup/export.php'))
                    ->setRequiredPermissions('backup[export]'),
            )
        ;

        if (Core::isLiveMode()) {
            return;
        }

        $backup->addSubpage(new Page('import', I18n::msg('backup_import'))
            ->addSubpage(new Page('upload', I18n::msg('backup_upload'))->setSubPath(Path::core('pages/backup/import.upload.php')))
            ->addSubpage(new Page('server', I18n::msg('backup_load_from_server'))->setSubPath(Path::core('pages/backup/import.server.php'))),
        );

        self::$pages['packages'] = new MainPage('system', 'packages', I18n::msg('addons'))
            ->setPath(Path::core('pages/addon/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(60)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-package-addon');

        self::$pages['media_manager'] = new MainPage('system', 'media_manager', I18n::msg('media_manager'))
            ->setPath(Path::core('pages/media_manager/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(70)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-media')
            ->addSubpage(new Page('types', I18n::msg('media_manager_subpage_types'))->setSubPath(Path::core('pages/media_manager/types.php')))
            ->addSubpage(new Page('settings', I18n::msg('media_manager_subpage_config'))->setSubPath(Path::core('pages/media_manager/settings.php')))
            ->addSubpage(new Page('overview', I18n::msg('media_manager_subpage_desc'))->setSubPath(Path::core('pages/media_manager/help.md')))
            ->addSubpage(new Page('clear_cache', I18n::msg('media_manager_subpage_clear_cache'))
                ->setItemAttr('class', 'pull-right')
                ->setLinkAttr('class', 'btn btn-delete')
                ->setHref(['page' => 'media_manager/types', 'func' => 'clear_cache']),
            )
        ;

        self::$pages['metainfo'] = new MainPage('system', 'metainfo', I18n::msg('metainfo'))
            ->setPath(Path::core('pages/metainfo/index.php'))
            ->setRequiredPermissions('isAdmin')
            ->setPrio(75)
            ->setPjax()
            ->setIcon('rex-icon rex-icon-metainfo')
            ->addSubpage(new Page('articles', I18n::msg('metainfo_articles')))
            ->addSubpage(new Page('categories', I18n::msg('metainfo_categories')))
            ->addSubpage(new Page('media', I18n::msg('metainfo_media')))
            ->addSubpage(new Page('clangs', I18n::msg('metainfo_clangs')))
            ->addSubpage(new Page('help', I18n::msg('metainfo_help'))->setSubPath(Path::core('pages/metainfo/help.md')))
        ;
    }

    public static function appendPackagePages(): void
    {
        $addons = Core::isSafeMode() ? Addon::getSetupAddons() : Addon::getActivatedAddons();
        foreach ($addons as $addon) {
            foreach ($addon->getPages() as $page) {
                self::registerAddonPage($page, $addon);
            }
        }
    }

    /**
     * Registers a top-level addon page and applies the path convention to it and its subpages.
     *
     * A page without an explicit path falls back to `pages/<key>.php`, or `pages/index.php` for the main page
     * whose key equals the addon name.
     */
    private static function registerAddonPage(Page $page, Addon $addon): void
    {
        $prefix = $page->getKey() === $addon->name ? '' : $page->getKey() . '.';

        if (!$page->hasPath()) {
            $page->setPath($addon->getPath('pages/' . ($prefix ?: 'index.') . 'php'));
        }
        self::$pages[$page->getKey()] = $page;

        self::pageSetSubPaths($page, $addon, $prefix);
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
            $page = $user->startPage;
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

    /** Includes the current page. A page may be provided by the core or an addon. */
    public static function includeCurrentPage(): void
    {
        $currentPage = self::requireCurrentPageObject();

        if (Request::isPJAXRequest() && !Request::isPJAXContainer('#rex-js-page-container')) {
            // non-core pjax containers should not have a layout.
            // they render their whole response on their own
            $currentPage->setHasLayout(false);
        }

        Timer::measure('Layout: top.php', function () {
            require Path::core('pages/layout/top.php');
        });

        self::includePath(Type::string($currentPage->getPath()));

        Timer::measure('Layout: bottom.php', function () {
            require Path::core('pages/layout/bottom.php');
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
        return Timer::measure('Page: ' . Path::relative($path), function () use ($path, $context) {
            foreach (Addon::getActivatedAddons() as $addon) {
                if (str_starts_with($path, $addon->path . DIRECTORY_SEPARATOR)) {
                    return $addon->includeFile($path, $context);
                }
            }

            $__context = $context;
            $__path = $path;

            unset($context, $path, $addon);

            extract($__context, EXTR_SKIP);

            return include $__path;
        });
    }
}
