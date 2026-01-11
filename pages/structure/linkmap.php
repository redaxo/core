<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\Linkmap\ArticleList;
use Redaxo\Core\Content\Linkmap\CategoryTree;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

// ------- Default Values

$openerInputField = Request::request('opener_input_field', 'string');
$openerInputFieldName = Request::request('opener_input_field_name', 'string');
$categoryId = Request::request('category_id', 'int');
$categoryId = Category::get($categoryId) ? $categoryId : 0;
$clang = Request::request('clang', 'int');
$clang = Language::exists($clang) ? $clang : Language::getStartId();

$pattern = '/[^a-z0-9_-]/i';
if (preg_match($pattern, $openerInputField, $match)) {
    throw new InvalidArgumentException(sprintf('Invalid character "%s" in opener_input_field.', $match[0]));
}
if (preg_match($pattern, $openerInputFieldName, $match)) {
    throw new InvalidArgumentException(sprintf('Invalid character "%s" in opener_input_field_name.', $match[0]));
}

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'opener_input_field' => $openerInputField,
    'opener_input_field_name' => $openerInputFieldName,
    'category_id' => $categoryId,
    'clang' => $clang,
]);

// ------- Build JS Functions

$funcBody = '';

if ('' != $openerInputField && '' == $openerInputFieldName) {
    $openerInputFieldName = $openerInputField . '_NAME';
}
if (str_starts_with($openerInputField, 'REX_LINKLIST_')) {
    $id = escape((string) substr($openerInputField, 13, strlen($openerInputField)), 'js');
    $funcBody .= 'var linklist = "REX_LINKLIST_SELECT_' . $id . '";
                             var linkid = link.replace("redaxo://","");
                 var source = opener.document.getElementById(linklist);
                 var sourcelength = source.options.length;

                             option = opener.document.createElement("OPTION");
                             option.text = name;
                             option.value = linkid;

                 source.options.add(option, sourcelength);
                 opener.writeREXLinklist(\'' . $id . '\');';
} else {
    $escapedOpenerInputField = escape($openerInputField, 'js');
    $escapedOpenerInputFieldName = escape($openerInputFieldName, 'js');
    $funcBody .= <<<JS
        var event = opener.jQuery.Event("rex:selectLink");
        opener.jQuery(window).trigger(event, [link, name]);
        if (!event.isDefaultPrevented()) {
            var linkid = link.replace("redaxo://","");
            window.opener.document.getElementById("$escapedOpenerInputField").value = linkid;
            window.opener.document.getElementById("$escapedOpenerInputFieldName").value = name;
            self.close();
        }
        JS;
}

// ------------------------ Print JS Functions

$retainEventHandlers = '';
if (!Request::isXmlHttpRequest()) {
    $retainEventHandlers = 'rex_retain_popup_event_handlers("rex:selectLink");';
}

?>
<script type="text/javascript" nonce="<?= Response::getNonce() ?>">
    <?= $retainEventHandlers ?>

    function insertLink(link,name){
        <?= $funcBody, "\n" ?>
    }
</script>

<?php

$isRoot = 0 === $categoryId;
$category = Category::get($categoryId);

$navigation = [];
if ($category) {
    foreach ($category->getParentTree() as $parent) {
        $n = [];
        $n['title'] = str_replace(' ', '&nbsp;', escape($parent->getName()));
        $n['href'] = $context->getUrl(['category_id' => $parent->getId()]);
        $navigation[] = $n;
    }
}

echo View::title('<i class="rex-icon rex-icon-linkmap"></i> Linkmap');

$title = '<a href="' . $context->getUrl(['category_id' => 0]) . '"><i class="rex-icon rex-icon-structure-root-level"></i> ' . I18n::msg('root_level') . '</a>';

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

$articleList = new ArticleList($context);
$panel = $articleList->getList($categoryId);

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('linkmap_articles'), false);
$fragment->setVar('content', $panel, false);
$content[] = $fragment->parse('core/page/section.php');

$fragment = new Fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('classes', ['col-sm-6', 'col-sm-6'], false);
echo $fragment->parse('core/page/grid.php');
