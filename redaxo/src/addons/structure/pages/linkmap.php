<?php

// ------- Default Values

$openerInputField = rex_request('opener_input_field', 'string');
$openerInputFieldName = rex_request('opener_input_field_name', 'string');
$categoryId = rex_request('category_id', 'int');
$categoryId = rex_category::get($categoryId) ? $categoryId : 0;
$clang = rex_request('clang', 'int');
$clang = rex_clang::exists($clang) ? $clang : rex_clang::getStartId();

$pattern = '/[^a-z0-9_-]/i';
if (preg_match($pattern, $openerInputField, $match)) {
    throw new InvalidArgumentException(sprintf('Invalid character "%s" in opener_input_field.', $match[0]));
}
if (preg_match($pattern, $openerInputFieldName, $match)) {
    throw new InvalidArgumentException(sprintf('Invalid character "%s" in opener_input_field_name.', $match[0]));
}

$context = new rex_context([
    'page' => rex_be_controller::getCurrentPage(),
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
    $id = rex_escape((string) substr($openerInputField, 13, strlen($openerInputField)), 'js');
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
    $escapedOpenerInputField = rex_escape($openerInputField, 'js');
    $escapedOpenerInputFieldName = rex_escape($openerInputFieldName, 'js');
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

// This function adds a Font Awesome check icon to a list item (link) when the user clicks on it.
$checkIconFuncBody = <<< JS
    document.addEventListener('click', function (e) {
        const item = e.target.closest('.list-with-icon > .list-group-item');
        if (!item) return;
        if (item.querySelector('.fa-check')) return;
        item.insertAdjacentHTML(
            'beforeend',
            '<i class="fa-solid fa-check" style="padding-left:5px"></i>'
        );
    });
JS;

// ------------------------ Print JS Functions

$retainEventHandlers = '';
if (!rex_request::isXmlHttpRequest()) {
    $retainEventHandlers = 'rex_retain_popup_event_handlers("rex:selectLink");';
}

?>
<script type="text/javascript" nonce="<?= rex_response::getNonce() ?>">
    <?= $retainEventHandlers ?>
    <?=$checkIconFuncBody?>
    function insertLink(link,name){
        <?= $funcBody, "\n" ?>
    }
</script>
<?php

$isRoot = 0 === $categoryId;
$category = rex_category::get($categoryId);

$navigation = [];
if ($category) {
    foreach ($category->getParentTree() as $parent) {
        $n = [];
        $n['title'] = str_replace(' ', '&nbsp;', rex_escape($parent->getName()));
        $n['href'] = $context->getUrl(['category_id' => $parent->getId()]);
        $navigation[] = $n;
    }
}

echo rex_view::title('<i class="rex-icon rex-icon-linkmap"></i> Linkmap');

$title = '<a href="' . $context->getUrl(['category_id' => 0]) . '"><i class="rex-icon rex-icon-structure-root-level"></i> ' . rex_i18n::msg('root_level') . '</a>';

$fragment = new rex_fragment();
$fragment->setVar('title', $title, false);
$fragment->setVar('items', $navigation, false);
echo $fragment->parse('core/navigations/breadcrumb.php');

$content = [];

$categoryTree = new rex_linkmap_category_tree($context);
$panel = $categoryTree->getTree($categoryId);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('linkmap_categories'), false);
$fragment->setVar('content', $panel, false);
$content[] = $fragment->parse('core/page/section.php');

$articleList = new rex_linkmap_article_list($context);
$panel = $articleList->getList($categoryId);

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('linkmap_articles'), false);
$fragment->setVar('content', $panel, false);
$content[] = $fragment->parse('core/page/section.php');

$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('classes', ['col-sm-6', 'col-sm-6'], false);
echo $fragment->parse('core/page/grid.php');
