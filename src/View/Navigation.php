<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Util\Str;

use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Klasse zum Erstellen von Navigationen.
 */
/*
 * Beispiel:
 *
 * UL, LI Navigation von der Rootebene aus,
 * 2 Ebenen durchgehen, Alle unternavis offen
 * und offline categorien nicht beachten
 *
 * Navigation:
 *
 * $nav = Navigation::factory();
 * $nav->setClasses(array('lev1', 'lev2', 'lev3'));
 * $nav->setLinkClasses(array('alev1', 'alev2', 'alev3'));
 * echo $nav->get(0,2,TRUE,TRUE);
 *
 * Sitemap:
 *
 * $nav = Navigation::factory();
 * $nav->show(0,-1,TRUE,TRUE);
 *
 * Breadcrump:
 *
 * $nav = Navigation::factory();
 * $nav->showBreadcrumb(true);
 */
class Navigation
{
    use FactoryTrait;

    private int $depth = -1; // Wieviele Ebene tief, ab der Startebene
    private bool $open = false; // alles aufgeklappt, z.b. Sitemap
    /** @var list<int> */
    private array $path = [];
    /** @var array<int, string> */
    private array $classes = [];
    /** @var array<int, string> */
    private array $linkclasses = [];
    /** @var list<array{metafield: string, value: int|string, type: string, depth: int|''}> */
    private array $filter = [];
    /** @var list<array{
     *     callback: callable(Category, int, array<int|string, int|string|list<string>>, array<int|string, int|string|list<string>>, string):bool,
     *     depth: int|''
     * }>
     */
    private array $callbacks = [];

    private int $currentArticleId = -1; // Aktueller Artikel
    private int $currentCategoryId = -1; // Aktuelle Katgorie

    private static bool $factoryCall = false;

    public function __construct()
    {
        if (!self::$factoryCall && self::class === static::class) {
            throw new LogicException(sprintf('Base class %s must be instantiated via %1$s::factory().', self::class));
        }

        self::$factoryCall = false;
    }

    public static function factory(): static
    {
        $class = self::getFactoryClass();
        self::$factoryCall = true;
        return new $class();
    }

    /**
     * Generiert eine Navigation.
     *
     * @param int $categoryId Id der Wurzelkategorie
     * @param int $depth Anzahl der Ebenen die angezeigt werden sollen
     * @param bool $open True, wenn nur Elemente der aktiven Kategorie angezeigt werden sollen, sonst FALSE
     * @param bool $ignoreOfflines FALSE, wenn offline Elemente angezeigt werden, sonst TRUE
     *
     * @return string
     */
    public function get($categoryId = 0, $depth = 3, $open = false, $ignoreOfflines = false)
    {
        if (!$this->_setActivePath()) {
            return '';
        }

        $this->depth = $depth;
        $this->open = $open;
        if ($ignoreOfflines) {
            $this->addFilter('status', 1, '==');
        }

        return $this->_getNavigation($categoryId);
    }

    /**
     * @see get()
     *
     * @param int $categoryId
     * @param int $depth
     * @param bool $open
     * @param bool $ignoreOfflines
     */
    public function show($categoryId = 0, $depth = 3, $open = false, $ignoreOfflines = false): void
    {
        echo $this->get($categoryId, $depth, $open, $ignoreOfflines);
    }

    /**
     * Generiert eine Breadcrumb-Navigation.
     *
     * @param string|false $startPageLabel Label der Startseite, falls FALSE keine Start-Page anzeigen
     * @param bool $includeCurrent True wenn der aktuelle Artikel enthalten sein soll, sonst FALSE
     * @param int $categoryId Id der Wurzelkategorie
     *
     * @return string
     */
    public function getBreadcrumb($startPageLabel, $includeCurrent = false, $categoryId = 0)
    {
        if (!$this->_setActivePath()) {
            return '';
        }

        $path = $this->path;

        $i = 1;
        $lis = [];

        if ($startPageLabel) {
            $link = '<a href="' . Url::article(Article::getSiteStartArticleId()) . '">' . escape($startPageLabel) . '</a>';
            $lis[] = $this->getBreadcrumbListItemTag($link, [
                'class' => 'rex-lvl' . $i,
            ], $i);
            ++$i;

            // StartArticle nicht doppelt anzeigen
            if (isset($path[0]) && $path[0] == Article::getSiteStartArticleId()) {
                unset($path[0]);
            }
        }

        $show = !$categoryId;
        foreach ($path as $pathItem) {
            if (!$show) {
                if ($pathItem == $categoryId) {
                    $show = true;
                } else {
                    continue;
                }
            }

            $cat = Category::require($pathItem);
            $link = $this->getBreadcrumbLinkTag($cat, escape($cat->name), [
                'href' => $cat->getUrl(),
            ], $i);
            $lis[] = $this->getBreadcrumbListItemTag($link, [
                'class' => 'rex-lvl' . $i,
            ], $i);
            ++$i;
        }

        if ($includeCurrent) {
            if ($art = Article::get($this->currentArticleId)) {
                if (!$art->isStartArticle()) {
                    $lis[] = $this->getBreadcrumbListItemTag(escape($art->name), [
                        'class' => 'rex-lvl' . $i,
                    ], $i);
                }
            } else {
                $cat = Category::require($this->currentArticleId);
                $lis[] = $this->getBreadcrumbListItemTag(escape($cat->name), [
                    'class' => 'rex-lvl' . $i,
                ], $i);
            }
        }

        return $this->getBreadcrumbListTag($lis, [
            'class' => [
                'rex-breadcrumb',
            ],
        ]);
    }

    /**
     * @see getBreadcrumb()
     *
     * @param string|false $startPageLabel
     * @param bool $includeCurrent
     * @param int $categoryId
     */
    public function showBreadcrumb($startPageLabel = false, $includeCurrent = false, $categoryId = 0): void
    {
        echo $this->getBreadcrumb($startPageLabel, $includeCurrent, $categoryId);
    }

    /** @param array<int, string> $classes */
    public function setClasses($classes): void
    {
        $this->classes = $classes;
    }

    /** @param array<int, string> $classes */
    public function setLinkClasses($classes): void
    {
        $this->linkclasses = $classes;
    }

    /**
     * Fügt einen Filter hinzu.
     *
     * @param string $metafield Datenbankfeld der Kategorie
     * @param int|string $value Wert für den Vergleich
     * @param string $type art des Vergleichs =/</
     * @param int|'' $depth "" wenn auf allen Ebenen, wenn definiert, dann wird der Filter nur auf dieser Ebene angewendet
     */
    public function addFilter($metafield = 'id', $value = '1', $type = '=', $depth = ''): void
    {
        $this->filter[] = ['metafield' => $metafield, 'value' => $value, 'type' => $type, 'depth' => $depth];
    }

    /**
     * Fügt einen Callback hinzu.
     *
     * @param callable(Category, int, array<(int|string), (int|string|list<string>)>, array<(int|string), (int|string|list<string>)>, string):bool $callback z.B. myFunc oder myClass::myMethod
     * @param int|'' $depth "" wenn auf allen Ebenen, wenn definiert, dann wird der Filter nur auf dieser Ebene angewendet
     *
     * @return $this
     */
    public function addCallback($callback, $depth = '')
    {
        if ('' != $callback) {
            $this->callbacks[] = ['callback' => $callback, 'depth' => $depth];
        }
        return $this;
    }

    /** @return bool */
    private function _setActivePath()
    {
        $articleId = Article::getCurrentId();
        if ($OOArt = Article::get($articleId)) {
            $this->path = $OOArt->path;
            $this->currentArticleId = $articleId;
            $this->currentCategoryId = $OOArt->categoryId ?? 0;
            return true;
        }

        return false;
    }

    /**
     * @param int $depth
     * @return bool
     */
    private function checkFilter(Category $category, $depth)
    {
        foreach ($this->filter as $f) {
            if ('' == $f['depth'] || $f['depth'] == $depth) {
                $mf = $category->getValue($f['metafield']);
                $va = $f['value'];
                switch ($f['type']) {
                    case '<>':
                    case '!=':
                        if ($mf == $va) {
                            return false;
                        }
                        break;
                    case '>':
                        if ($mf <= $va) {
                            return false;
                        }
                        break;
                    case '<':
                        if ($mf >= $va) {
                            return false;
                        }
                        break;
                    case '=>':
                    case '>=':
                        if ($mf < $va) {
                            return false;
                        }
                        break;
                    case '=<':
                    case '<=':
                        if ($mf > $va) {
                            return false;
                        }
                        break;
                    case 'regex':
                        if (!preg_match((string) $va, (string) $mf)) {
                            return false;
                        }
                        break;
                    case '=':
                    case '==':
                    default:
                        // =
                        if ($mf != $va) {
                            return false;
                        }
                }
            }
        }
        return true;
    }

    /**
     * @param int $depth
     * @param array<int|string, int|string|list<string>> $li
     * @param array<int|string, int|string|list<string>> $a
     * @param string $aContent
     * @return bool
     */
    private function checkCallbacks(Category $category, $depth, &$li, &$a, &$aContent)
    {
        foreach ($this->callbacks as $c) {
            if ('' == $c['depth'] || $c['depth'] == $depth) {
                $callback = $c['callback'];
                if (is_string($callback)) {
                    $callback = explode('::', $callback, 2);
                    if (count($callback) < 2) {
                        $callback = $callback[0];
                    }
                }
                if (is_array($callback) && count($callback) > 1) {
                    [$class, $method] = $callback;
                    if (is_object($class)) {
                        $result = $class->$method($category, $depth, $li, $a, $aContent);
                    } else {
                        $result = $class::$method($category, $depth, $li, $a, $aContent);
                    }
                } else {
                    $result = $callback($category, $depth, $li, $a, $aContent);
                }
                if (!$result) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param int $categoryId
     * @param int $depth
     *
     * @return string
     */
    protected function _getNavigation($categoryId, $depth = 1)
    {
        if ($categoryId < 1) {
            $navObj = Category::getRootCategories();
        } else {
            $navObj = Category::get($categoryId)->getChildren();
        }

        $lis = [];
        foreach ($navObj as $nav) {
            $li = [];
            $a = [];
            $li['class'] = [];
            $a['class'] = [];
            $a['href'] = [$nav->getUrl()];
            $aContent = escape($nav->name);
            if ($this->checkFilter($nav, $depth) && $this->checkCallbacks($nav, $depth, $li, $a, $aContent)) {
                $li['class'][] = 'rex-article-' . $nav->id;
                // classes abhaengig vom pfad
                if ($nav->id == $this->currentCategoryId) {
                    $li['class'][] = 'rex-current';
                    $a['class'][] = 'rex-current';
                } elseif (in_array($nav->id, $this->path)) {
                    $li['class'][] = 'rex-active';
                    $a['class'][] = 'rex-active';
                } else {
                    $li['class'][] = 'rex-normal';
                }
                if (isset($this->linkclasses[$depth - 1])) {
                    $a['class'][] = $this->linkclasses[$depth - 1];
                }
                if (isset($this->classes[$depth - 1])) {
                    $li['class'][] = $this->classes[$depth - 1];
                }

                $link = $this->getLinkTag($nav, $aContent, $a, $depth);

                ++$depth;
                if (
                    ($this->open || $nav->id == $this->currentCategoryId || in_array($nav->id, $this->path))
                    && ($this->depth >= $depth || $this->depth < 0)
                ) {
                    $link .= "\n" . $this->_getNavigation($nav->id, $depth);
                }
                --$depth;
                $lis[] = $this->getListItemTag($nav, $link, $li, $depth);
            }
        }
        if (count($lis) > 0) {
            return $this->getListTag($lis, [
                'class' => [
                    'rex-navi' . $depth,
                    'rex-navi-depth-' . $depth,
                    'rex-navi-has-' . count($lis) . '-elements',
                ],
            ], $depth);
        }
        return '';
    }

    /**
     * @param list<string> $items
     * @param array<int|string, int|string|list<string>> $attributes
     */
    protected function getBreadcrumbListTag(array $items, array $attributes): string
    {
        return '<ul' . Str::buildAttributes($attributes) . ">\n" . implode('', $items) . "</ul>\n";
    }

    /** @param array<int|string, int|string|list<string>> $attributes */
    protected function getBreadcrumbListItemTag(string $item, array $attributes, int $depth): string
    {
        return '<li' . Str::buildAttributes($attributes) . '>' . $item . "</li>\n";
    }

    /** @param array<int|string, int|string|list<string>> $attributes */
    protected function getBreadcrumbLinkTag(Category $category, string $content, array $attributes, int $depth): string
    {
        if (!isset($attributes['href'])) {
            $attributes['href'] = $category->getUrl();
        }

        return '<a' . Str::buildAttributes($attributes) . '>' . $content . '</a>';
    }

    /**
     * @param list<string> $items
     * @param array<int|string, int|string|list<string>> $attributes
     */
    protected function getListTag(array $items, array $attributes, int $depth): string
    {
        return '<ul' . Str::buildAttributes($attributes) . ">\n" . implode('', $items) . "</ul>\n";
    }

    /** @param array<int|string, int|string|list<string>> $attributes */
    protected function getListItemTag(Category $category, string $item, array $attributes, int $depth): string
    {
        return '<li' . Str::buildAttributes($attributes) . '>' . $item . "</li>\n";
    }

    /** @param array<int|string, int|string|list<string>> $attributes */
    protected function getLinkTag(Category $category, string $content, array $attributes, int $depth): string
    {
        if (!isset($attributes['href'])) {
            $attributes['href'] = $category->getUrl();
        }

        return '<a' . Str::buildAttributes($attributes) . '>' . $content . '</a>';
    }
}
