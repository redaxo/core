<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\Linkmap\ArticleList;
use Redaxo\Core\Content\Linkmap\CategoryTree;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

// ------- Default Values

$picker = Request::request('picker', 'string');
$categoryId = Request::request('category_id', 'int');
$categoryId = Category::get($categoryId) ? $categoryId : 0;
$languageId = Request::request('language', 'int');
$languageId = Language::exists($languageId) ? $languageId : Language::getStartId();

if (!in_array($picker, ['', 'single', 'multiple'], true)) {
    throw new InvalidArgumentException(sprintf('Invalid picker mode "%s".', $picker));
}

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'picker' => $picker,
    'category_id' => $categoryId,
    'language' => $languageId,
]);

$isRoot = 0 === $categoryId;
$category = Category::get($categoryId);

$navigation = [];
if ($category) {
    foreach ($category->getParentTree() as $parent) {
        $n = [];
        $n['title'] = str_replace(' ', '&nbsp;', escape($parent->name));
        $n['href'] = $context->getUrl(['category_id' => $parent->id]);
        $navigation[] = $n;
    }
}

echo View::title('<i class="rex-icon rex-icon-linkmap" aria-hidden="true"></i> Linkmap');

$title = '<a href="' . $context->getUrl(['category_id' => 0]) . '"><i class="rex-icon rex-icon-structure-root-level" aria-hidden="true"></i> ' . I18n::msg('root_level') . '</a>';

$fragment = new Fragment();
$fragment->setVar('title', $title, false);
$fragment->setVar('items', $navigation, false);
echo $fragment->parse('core/navigations/breadcrumb.php');

$content = [];

$categoryTree = new CategoryTree($context);
$panel = $categoryTree->getTree($categoryId);

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('linkmap_categories'), false);
$fragment->setVar('content', $panel, false);
$content[] = $fragment->parse('core/page/section.php');

$articleList = new ArticleList();
$panel = $articleList->getList($categoryId);

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('linkmap_articles'), false);
$fragment->setVar('content', $panel, false);
$content[] = $fragment->parse('core/page/section.php');

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('spans', ['sm:6', 'sm:6'], false);
echo $fragment->parse('core/page/grid.php');
