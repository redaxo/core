<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Content\ApiFunction\ArticleAdd;
use Redaxo\Core\Content\ApiFunction\ArticleDelete;
use Redaxo\Core\Content\ApiFunction\ArticleEdit;
use Redaxo\Core\Content\ApiFunction\ArticleStatusChange;
use Redaxo\Core\Content\ApiFunction\CategoryAdd;
use Redaxo\Core\Content\ApiFunction\CategoryDelete;
use Redaxo\Core\Content\ApiFunction\CategoryEdit;
use Redaxo\Core\Content\ApiFunction\CategoryStatusChange;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\StructureContext;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Select\TemplateSelect;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$structureContext = new StructureContext([
    'category_id' => Request::request('category_id', 'int'),
    'article_id' => Request::request('article_id', 'int'),
    'clang_id' => Request::request('clang', 'int'),
    'ctype_id' => Request::request('ctype', 'int'),
    'artstart' => Request::request('artstart', 'int'),
    'catstart' => Request::request('catstart', 'int'),
    'edit_id' => Request::request('edit_id', 'int'),
    'function' => Request::request('function', 'string'),
    'rows_per_page' => Core::getProperty('rows_per_page', 50),
]);

$user = Core::requireUser();

if (0 == $structureContext->getClangId()) {
    if (Language::exists(0)) {
        echo Message::error('Oooops. Your clang ids start with <code>0</code>. Looks like a broken REDAXO 4.x to 5.x upgrade. Please update all your database tables, php code (if there are any hard coded clang ids) aswell as additional configurations in add-ons, e.g. YRewrite. You may start with updating those tables: <code>rex_article</code>, <code>rex_article_slice</code>, <code>rex_clang</code>, by increasing every clang id <code>+ 1</code>.');
        exit;
    }
    echo Message::error('You have no permission to access this area');
    exit;
}

// --------------------- Extension Point
echo Extension::registerPoint(new ExtensionPoint('PAGE_STRUCTURE_HEADER_PRE', '', [
    'context' => $structureContext->getContext(),
]));

// --------------------------------------------- TITLE
echo View::title(I18n::msg('title_structure'));

// --------------------------------------------- Languages
echo View::clangSwitchAsButtons($structureContext->getContext());

// --------------------------------------------- Path
$categoryId = $structureContext->getCategoryId();
$clang = $structureContext->getClangId();
echo View::structureBreadcrumb($categoryId, $structureContext->getArticleId(), $clang);

// -------------- STATUS_TYPE Map
$catStatusTypes = CategoryHandler::statusTypes();
$artStatusTypes = ArticleHandler::statusTypes();

// --------------------------------------------- API MESSAGES
echo ApiFunction::getMessage();

// --------------------------------------------- KATEGORIE LISTE
$catName = I18n::msg('root_level');
$category = Category::get($structureContext->getCategoryId(), $structureContext->getClangId());
if ($category) {
    $catName = $category->getName();
}

$addCategory = '';
if ($user->hasPerm('addCategory[]') && $structureContext->hasCategoryPermission()) {
    $addCategory = '<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['function' => 'add_cat', 'catstart' => $structureContext->getCatStart()]) . '"' . Core::getAccesskey(I18n::msg('add_category'), 'add') . '><i class="rex-icon rex-icon-add-category"></i></a>';
}

$dataColspan = 5;

// --------------------- Extension Point
echo Extension::registerPoint(new ExtensionPoint('PAGE_STRUCTURE_HEADER', '', [
    'category_id' => $structureContext->getCategoryId(),
    'clang' => $structureContext->getClangId(),
]));

// --------------------- COUNT CATEGORY ROWS

$KAT = Sql::factory();
// $KAT->setDebug();
if (count($structureContext->getMountpoints()) > 0 && 0 == $structureContext->getCategoryId()) {
    $parentIds = $KAT->in($structureContext->getMountpoints());
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM ' . Core::getTablePrefix() . 'article WHERE id IN (' . $parentIds . ') AND startarticle=1 AND clang_id=?', [$structureContext->getClangId()]);
} else {
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM ' . Core::getTablePrefix() . 'article WHERE parent_id=? AND startarticle=1 AND clang_id=?', [$structureContext->getCategoryId(), $structureContext->getClangId()]);
}

// --------------------- ADD PAGINATION

$catPager = new Pager($structureContext->getRowsPerPage(), 'catstart');
$catPager->setRowCount((int) $KAT->getValue('rowCount'));
$catFragment = new Fragment();
$catFragment->setVar('urlprovider', $structureContext->getContext());
$catFragment->setVar('pager', $catPager);
echo $catFragment->parse('core/navigations/pagination.php');

// --------------------- GET THE DATA

if (count($structureContext->getMountpoints()) > 0 && 0 == $structureContext->getCategoryId()) {
    $parentIds = $KAT->in($structureContext->getMountpoints());

    $KAT->setQuery('SELECT parent_id FROM ' . Core::getTable('article') . ' WHERE id IN (' . $parentIds . ') GROUP BY parent_id');
    $orderBy = $KAT->getRows() > 1 ? 'catname' : 'catpriority';

    $KAT->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE id IN (' . $parentIds . ') AND startarticle=1 AND clang_id = ? ORDER BY ' . $orderBy . ' LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage(), [$structureContext->getClangId()]);
} else {
    $KAT->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE parent_id = ? AND startarticle=1 AND clang_id = ? ORDER BY catpriority LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage(), [$structureContext->getCategoryId(), $structureContext->getClangId()]);
}

$trStatusClass = 'rex-status';
$echo = '';
// ---------- INLINE THE EDIT/ADD FORM
if ('add_cat' == $structureContext->getFunction() || 'edit_cat' == $structureContext->getFunction()) {
    $echo .= '
    <form action="' . $structureContext->getContext()->getUrl(['catstart' => $structureContext->getCatStart()]) . '" method="post">
        <fieldset>

            <input type="hidden" name="edit_id" value="' . $structureContext->getEditId() . '" />';
}

$canEdit = $user->hasPerm('editCategory[]');
$canDelete = $user->hasPerm('deleteCategory[]');
$colspan = (int) $canEdit + (int) $canDelete + 1;

// --------------------- PRINT CATS/SUBCATS
$echo .= '
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon">' . $addCategory . '</th>
                        <th class="rex-table-id">' . I18n::msg('header_id') . '</th>
                        <th class="rex-table-category">' . I18n::msg('header_category') . '</th>
                        <th class="rex-table-priority">' . I18n::msg('header_priority') . '</th>
                        <th class="rex-table-action" colspan="' . $colspan . '">' . I18n::msg('header_status') . '</th>
                    </tr>
                </thead>
                <tbody>';

// --------------------- KATEGORIE ADD FORM

if ('add_cat' == $structureContext->getFunction() && $user->hasPerm('addCategory[]') && $structureContext->hasCategoryPermission()) {
    $metaButtons = Extension::registerPoint(new ExtensionPoint('CAT_FORM_BUTTONS', '', [
        'id' => $structureContext->getCategoryId(),
        'clang' => $structureContext->getClangId(),
    ]));
    $addButtons = CategoryAdd::getHiddenFields() . '
        <input type="hidden" name="parent-category-id" value="' . $structureContext->getCategoryId() . '" />
        <button class="btn btn-save" type="submit" name="category-add-button"' . Core::getAccesskey(I18n::msg('add_category'), 'save') . '>' . I18n::msg('add_category') . '</button>';

    $class = 'mark';

    $echo .= '
                <tr class="' . $class . '">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-category"></i></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">-</td>
                    <td class="rex-table-category" data-title="' . I18n::msg('header_category') . '"><input class="form-control" type="text" name="category-name" class="rex-js-autofocus" required maxlength="255" autofocus /></td>
                    <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '"><input class="form-control" type="number" name="category-position" value="' . ($catPager->getRowCount() + 1) . '" required min="1" inputmode="numeric" /></td>
                    <td class="rex-table-action" colspan="' . $colspan . '">' . $metaButtons . $addButtons . '</td>
                </tr>';

    // ----- EXTENSION POINT
    $echo .= Extension::registerPoint(new ExtensionPoint('CAT_FORM_ADD', '', [
        'id' => $structureContext->getCategoryId(),
        'clang' => $structureContext->getClangId(),
        'data_colspan' => ($dataColspan + 1),
    ]));
}

// --------------------- KATEGORIE LIST
if ($KAT->getRows() > 0) {
    for ($i = 0; $i < $KAT->getRows(); ++$i) {
        $iCategoryId = (int) $KAT->getValue('id');

        $katLink = $structureContext->getContext()->getUrl(['category_id' => $iCategoryId]);

        /** @var Category $katObject */
        $katObject = Category::get($iCategoryId);
        $katHasChildElements = (count($katObject->getChildren()) > 0 || count($katObject->getArticles()) > 1); // contains child categories or articles other than the start article
        $katIconClass = $katHasChildElements ? 'rex-icon-category' : 'rex-icon-category-without-elements';
        $katIconTitle = $katHasChildElements ? I18n::msg('category_has_child_elements') : I18n::msg('category_without_child_elements');
        $katIconTd = '<td class="rex-table-icon"><a class="rex-link-expanded" href="' . $katLink . '" title="' . escape($KAT->getValue('catname')) . '"><i class="rex-icon ' . $katIconClass . '" title="' . $katIconTitle . '"></i></a></td>';

        $status = (int) $KAT->getValue('status');
        $katStatus = $catStatusTypes[$status][0];
        $statusClass = $catStatusTypes[$status][1];
        $statusIcon = $catStatusTypes[$status][2];
        $dataCatStatus = 'data-status="' . $status . '"';

        $tdLayoutClass = '';
        if ($structureContext->hasCategoryPermission()) {
            if ($user->hasPerm('publishCategory[]')) {
                $tdLayoutClass = 'rex-table-action-no-dropdown';
                if (count($catStatusTypes) > 2) {
                    $tdLayoutClass = 'rex-table-action-dropdown';
                    $katStatus = '<div class="dropdown"><a href="#" class="dropdown-toggle ' . $statusClass . '" type="button" data-toggle="dropdown"><i class="rex-icon ' . $statusIcon . '"></i>&nbsp;' . $katStatus . '&nbsp;<span class="caret"></span></a><ul class="dropdown-menu dropdown-menu-right">';
                    foreach ($catStatusTypes as $catStatusKey => $catStatusType) {
                        $katStatus .= '<li><a class="' . $catStatusType[1] . '" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->getCatStart(), 'cat_status' => $catStatusKey] + CategoryStatusChange::getUrlParams()) . '">' . $catStatusType[0] . '</a></li>';
                    }
                    $katStatus .= '</ul></div>';
                } else {
                    $katStatus = '<a class="rex-link-expanded ' . $statusClass . '" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->getCatStart()] + CategoryStatusChange::getUrlParams()) . '"><i class="rex-icon ' . $statusIcon . '"></i>&nbsp;' . $katStatus . '</a>';
                }
            } else {
                $katStatus = '<span class="' . $statusClass . ' text-muted"><i class="rex-icon ' . $statusIcon . '"></i> ' . $katStatus . '</span>';
            }

            if ($canEdit && $structureContext->getEditId() == $iCategoryId && 'edit_cat' == $structureContext->getFunction()) {
                // --------------------- KATEGORIE EDIT FORM

                // ----- EXTENSION POINT
                $metaButtons = Extension::registerPoint(new ExtensionPoint('CAT_FORM_BUTTONS', '', [
                    'id' => $structureContext->getEditId(),
                    'clang' => $structureContext->getClangId(),
                ]));

                $addButtons = CategoryEdit::getHiddenFields() . '
                <input type="hidden" name="category-id" value="' . $structureContext->getEditId() . '" />
                <button class="btn btn-save" type="submit" name="category-edit-button"' . Core::getAccesskey(I18n::msg('save_category'), 'save') . '>' . I18n::msg('save_category') . '</button>';

                $class = 'mark';
                if ('' != $metaButtons) {
                    $class .= ' rex-has-metainfo';
                }

                $echo .= '
                    <tr class="' . $class . '" ' . $dataCatStatus . '>
                        ' . $katIconTd . '
                        <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . $iCategoryId . '</td>
                        <td class="rex-table-category" data-title="' . I18n::msg('header_category') . '"><input class="form-control" type="text" name="category-name" value="' . escape($KAT->getValue('catname')) . '" class="rex-js-autofocus" required maxlength="255" autofocus /></td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '"><input class="form-control" type="number" name="category-position" value="' . escape($KAT->getValue('catpriority')) . '" required min="1" inputmode="numeric" /></td>
                        <td class="rex-table-action" colspan="' . $colspan . '">' . $metaButtons . $addButtons . '</td>
                    </tr>';

                // ----- EXTENSION POINT
                $echo .= Extension::registerPoint(new ExtensionPoint('CAT_FORM_EDIT', '', [
                    'id' => $structureContext->getEditId(),
                    'clang' => $structureContext->getClangId(),
                    'category' => $KAT,
                    'catname' => $KAT->getValue('catname'),
                    'catpriority' => $KAT->getValue('catpriority'),
                    'data_colspan' => ($dataColspan + 1),
                ]));
            } else {
                // --------------------- KATEGORIE WITH WRITE

                $echo .= '
                    <tr class="' . $trStatusClass . '" ' . $dataCatStatus . '>
                        ' . $katIconTd . '
                        <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . $iCategoryId . '</td>
                        <td class="rex-table-category" data-title="' . I18n::msg('header_category') . '"><a class="rex-link-expanded" href="' . $katLink . '">' . escape($KAT->getValue('catname')) . '</a></td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '">' . escape($KAT->getValue('catpriority')) . '</td>';
                if ($canEdit) {
                    $echo .= '
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['edit_id' => $iCategoryId, 'function' => 'edit_cat', 'catstart' => $structureContext->getCatStart()]) . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</a></td>';
                }
                if ($canDelete) {
                    $echo .= '
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->getCatStart()] + CategoryDelete::getUrlParams()) . '" data-confirm="' . I18n::msg('structure_delete_all_clangs') . '"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a></td>';
                }
                $echo .= '
                        <td class="rex-table-action ' . $tdLayoutClass . '">' . $katStatus . '</td>
                    </tr>';
            }
        } elseif ($user->getComplexPerm('structure')->hasCategoryPerm($iCategoryId)) {
            // --------------------- KATEGORIE WITH READ

            $echo .= '
                    <tr class="' . $trStatusClass . '" ' . $dataCatStatus . '>
                        ' . $katIconTd . '
                        <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . $iCategoryId . '</td>
                        <td class="rex-table-category" data-title="' . I18n::msg('header_category') . '"><a class="rex-link-expanded" href="' . $katLink . '">' . escape($KAT->getValue('catname')) . '</a></td>
                        <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '">' . escape($KAT->getValue('catpriority')) . '</td>';
            if ($canEdit) {
                $echo .= '
                        <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</span></td>';
            }
            if ($canDelete) {
                $echo .= '
                        <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</span></td>';
            }
            $echo .= '
                        <td class="rex-table-action"><span class="' . $statusClass . ' text-muted"><i class="rex-icon ' . $statusIcon . '"></i> ' . $katStatus . '</span></td>
                    </tr>';
        }

        $KAT->next();
    }
} else {
    $echo .= '
                <tr>
                    <td>&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td colspan="' . $colspan . '"></td>
                </tr>';
}

$echo .= '
            </tbody>
        </table>';

if ('add_cat' == $structureContext->getFunction() || 'edit_cat' == $structureContext->getFunction()) {
    $echo .= '
    </fieldset>
</form>';
}

$heading = I18n::msg('structure_categories_caption', $catName);
if (0 == $structureContext->getCategoryId()) {
    $heading = I18n::msg('structure_root_level_categories_caption');
}
$fragment = new Fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');

// --------------------------------------------- ARTIKEL LISTE

$echo = '';

// --------------------- READ TEMPLATES

$templateSelect = new TemplateSelect($categoryId, $clang);
if ($structureContext->getCategoryId() > 0 || (0 == $structureContext->getCategoryId() && !$user->getComplexPerm('structure')->hasMountpoints())) {
    $templateSelect->setName('template_id');
    $templateSelect->setSize(1);
    $templateSelect->setStyle('class="form-control selectpicker"');

    $templateNames = $templateSelect->getTemplates();
    $templateNames[0] = I18n::msg('template_default_name');

    // --------------------- ARTIKEL LIST
    $artAddLink = '';
    if ($user->hasPerm('addArticle[]') && $structureContext->hasCategoryPermission()) {
        $artAddLink = '<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['function' => 'add_art', 'artstart' => $structureContext->getArtStart()]) . '"' . Core::getAccesskey(I18n::msg('article_add'), 'add_2') . '><i class="rex-icon rex-icon-add-article"></i></a>';
    }

    $articleOrderBy = Extension::registerPoint(new ExtensionPoint('PAGE_STRUCTURE_ARTICLE_ORDER_BY', 'priority, name', [
        'category_id' => $structureContext->getCategoryId(),
        'article_id' => $structureContext->getArticleId(),
        'clang' => $structureContext->getClangId(),
    ]));

    // ---------- COUNT DATA
    $sql = Sql::factory();
    // $sql->setDebug();
    $sql->setQuery('
        SELECT COUNT(*) as artCount
        FROM ' . Core::getTablePrefix() . 'article
        WHERE
            ((parent_id = :category_id AND startarticle=0) OR (id = :category_id AND startarticle=1))
            AND clang_id = :clang_id
    ', [
        'category_id' => $structureContext->getCategoryId(),
        'clang_id' => $structureContext->getClangId(),
    ]);

    // --------------------- ADD PAGINATION

    $artPager = new Pager($structureContext->getRowsPerPage(), 'artstart');
    $artPager->setRowCount((int) $sql->getValue('artCount'));
    $artFragment = new Fragment();
    $artFragment->setVar('urlprovider', $structureContext->getContext());
    $artFragment->setVar('pager', $artPager);
    echo $artFragment->parse('core/navigations/pagination.php');

    // ---------- READ DATA
    $sql->setQuery('
        SELECT *
        FROM ' . Core::getTablePrefix() . 'article
        WHERE
            ((parent_id = :category_id AND startarticle=0) OR (id = :category_id AND startarticle=1))
            AND clang_id = :clang_id
        ORDER BY
            ' . $articleOrderBy . '
        LIMIT ' . $artPager->getCursor() . ',' . $artPager->getRowsPerPage(),
        [
            'category_id' => $structureContext->getCategoryId(),
            'clang_id' => $structureContext->getClangId(),
        ],
    );

    // ---------- INLINE THE EDIT/ADD FORM
    if ('add_art' == $structureContext->getFunction() || 'edit_art' == $structureContext->getFunction()) {
        $echo .= '
        <form action="' . $structureContext->getContext()->getUrl(['artstart' => $structureContext->getArtStart()]) . '" method="post">
            <fieldset>';
    }

    // ----------- PRINT OUT THE ARTICLES

    $echo .= '
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="rex-table-icon">' . $artAddLink . '</th>
                        <th class="rex-table-id">' . I18n::msg('header_id') . '</th>
                        <th class="rex-table-article-name">' . I18n::msg('header_article_name') . '</th>
                        <th class="rex-table-template">' . I18n::msg('header_template') . '</th>
                        <th class="rex-table-date">' . I18n::msg('header_date') . '</th>
                        <th class="rex-table-priority">' . I18n::msg('header_priority') . '</th>
                        <th class="rex-table-action" colspan="3">' . I18n::msg('header_status') . '</th>
                    </tr>
                </thead>
                <tbody>
                ';

    $canEdit = $user->hasPerm('editArticle[]');
    $canDelete = $user->hasPerm('deleteArticle[]');
    $colspan = (int) $canEdit + (int) $canDelete + 1;

    // --------------------- ARTIKEL ADD FORM
    if ('add_art' == $structureContext->getFunction() && $user->hasPerm('addArticle[]') && $structureContext->hasCategoryPermission()) {
        $templateSelect->setSelectedFromStartArticle();
        $tmplTd = '<td class="rex-table-template" data-title="' . I18n::msg('header_template') . '">' . $templateSelect->get() . '</td>';

        $echo .= '<tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-article"></i></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">-</td>
                    <td class="rex-table-article-name" data-title="' . I18n::msg('header_article_name') . '"><input class="form-control" type="text" name="article-name" required maxlength="255" autofocus /></td>
                    ' . $tmplTd . '
                    <td class="rex-table-date" data-title="' . I18n::msg('header_date') . '">' . Formatter::intlDate(time()) . '</td>
                    <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '"><input class="form-control" type="number" name="article-position" value="' . ($artPager->getRowCount() + 1) . '" required min="1" inputmode="numeric" /></td>
                    <td class="rex-table-action" colspan="' . $colspan . '">' . ArticleAdd::getHiddenFields() . '<button class="btn btn-save" type="submit" name="artadd_function"' . Core::getAccesskey(I18n::msg('article_add'), 'save') . '>' . I18n::msg('article_add') . '</button></td>
                </tr>
                            ';
    } elseif (0 === $sql->getRows()) {
        $echo .= '<tr>
            <td>&nbsp;</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td colspan="' . $colspan . '"></td>
        </tr>';
    }

    // --------------------- ARTIKEL LIST
    for ($i = 0; $i < $sql->getRows(); ++$i) {
        if ($sql->getValue('id') == Article::getSiteStartArticleId()) {
            $class = ' rex-icon-sitestartarticle';
        } elseif (1 == $sql->getValue('startarticle')) {
            $class = ' rex-icon-startarticle';
        } else {
            $class = ' rex-icon-article';
        }
        $dataArtid = 'data-article-id="' . $sql->getValue('id') . '"';
        $dataArtStatus = 'data-status="' . ((int) $sql->getValue('status')) . '"';

        $classStartarticle = '';
        if (1 == $sql->getValue('startarticle')) {
            $classStartarticle = ' rex-startarticle';
        }

        // --------------------- ARTIKEL EDIT FORM

        if ($canEdit && 'edit_art' == $structureContext->getFunction() && $sql->getValue('id') == $structureContext->getArticleId() && $structureContext->hasCategoryPermission()) {
            $templateSelect->setSelected($sql->getValue('template_id'));
            $tmplTd = '<td class="rex-table-template" data-title="' . I18n::msg('header_template') . '">' . $templateSelect->get() . '</td>';

            $echo .= '<tr class="mark' . $classStartarticle . ' ' . $trStatusClass . '">
                            <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $sql->getValue('id')]) . '" title="' . escape($sql->getValue('name')) . '"><i class="rex-icon' . $class . '"></i></a></td>
                            <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . (int) $sql->getValue('id') . '</td>
                            <td class="rex-table-article-name" data-title="' . I18n::msg('header_article_name') . '"><input class="form-control" type="text" name="article-name" value="' . escape($sql->getValue('name')) . '" required maxlength="255" autofocus /></td>
                            ' . $tmplTd . '
                            <td class="rex-table-date" data-title="' . I18n::msg('header_date') . '">' . Formatter::intlDate($sql->getDateTimeValue('createdate')) . '</td>
                            <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '"><input class="form-control" type="number" name="article-position" value="' . escape($sql->getValue('priority')) . '" required min="1" inputmode="numeric" /></td>
                            <td class="rex-table-action" colspan="' . $colspan . '">' . ArticleEdit::getHiddenFields() . '<button class="btn btn-save" type="submit" name="artedit_function"' . Core::getAccesskey(I18n::msg('article_save'), 'save') . '>' . I18n::msg('article_save') . '</button></td>
                        </tr>';
        } elseif ($structureContext->hasCategoryPermission()) {
            // --------------------- ARTIKEL NORMAL VIEW | EDIT AND ENTER

            $status = (int) $sql->getValue('status');
            $articleStatus = $artStatusTypes[$status][0];
            $articleClass = $artStatusTypes[$status][1];
            $articleIcon = $artStatusTypes[$status][2];
            $dataArtStatus = 'data-status="' . $status . '"';

            $addExtra = '';
            if ($canEdit) {
                $addExtra = '<td class="rex-table-action"><a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'function' => 'edit_art', 'artstart' => $structureContext->getArtStart()]) . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</a></td>';
            }

            if (1 == $sql->getValue('startarticle')) {
                if ($canDelete) {
                    $addExtra .= '<td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</span></td>';
                }

                $addExtra .= '<td class="rex-table-action"><span class="' . $articleClass . ' text-muted"><i class="rex-icon ' . $articleIcon . '"></i> ' . $articleStatus . '</span></td>';
            } else {
                if ($canDelete) {
                    $addExtra .= '<td class="rex-table-action"><a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'artstart' => $structureContext->getArtStart()] + ArticleDelete::getUrlParams()) . '" data-confirm="' . I18n::msg('structure_delete_all_clangs') . '"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a></td>';
                }

                $tdLayoutClass = '';
                if ($user->hasPerm('publishArticle[]')) {
                    $tdLayoutClass = 'rex-table-action-no-dropdown';

                    if (count($artStatusTypes) > 2) {
                        $tdLayoutClass = 'rex-table-action-dropdown';
                        $articleStatus = '<div class="dropdown"><a href="#" class="dropdown-toggle ' . $articleClass . '" type="button" data-toggle="dropdown"><i class="rex-icon ' . $articleIcon . '"></i>&nbsp;' . $articleStatus . '&nbsp;<span class="caret"></span></a><ul class="dropdown-menu dropdown-menu-right">';
                        foreach ($artStatusTypes as $artStatusKey => $artStatusType) {
                            $articleStatus .= '<li><a  class="' . $artStatusType[1] . '" href="' . $structureContext->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'artstart' => $structureContext->getArtStart(), 'art_status' => $artStatusKey] + ArticleStatusChange::getUrlParams()) . '">' . $artStatusType[0] . '</a></li>';
                        }
                        $articleStatus .= '</ul></div>';
                    } else {
                        $articleStatus = '<a class="' . $articleClass . '" href="' . $structureContext->getContext()->getUrl(['article_id' => $sql->getValue('id'), 'artstart' => $structureContext->getArtStart()] + ArticleStatusChange::getUrlParams()) . '"><i class="rex-icon ' . $articleIcon . '"></i>&nbsp;' . $articleStatus . '</a>';
                    }
                } else {
                    $articleStatus = '<span class="' . $articleClass . ' text-muted"><i class="rex-icon ' . $articleIcon . '"></i> ' . $articleStatus . '</span>';
                }

                $addExtra .= '<td class="rex-table-action ' . $tdLayoutClass . '">' . $articleStatus . '</td>';
            }

            $editModeUrl = $structureContext->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $sql->getValue('id'), 'mode' => 'edit']);

            $tmplTd = '';
            $tmpl = escape($templateNames[(int) $sql->getValue('template_id')] ?? '');
            $tmplTd = '<td class="rex-table-template" data-title="' . I18n::msg('header_template') . '">
            <div class="rex-truncate rex-truncate-target" title="' . $tmpl . '" >' . $tmpl . '</div></td>';

            $echo .= '<tr ' . $dataArtStatus . ' ' . $dataArtid . (('' != $classStartarticle) ? ' class="' . trim($classStartarticle) . ' ' . $trStatusClass . '"' : ' class="' . $trStatusClass . '"') . '>
                            <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $editModeUrl . '" title="' . escape($sql->getValue('name')) . '"><i class="rex-icon' . $class . '"></i></a></td>
                            <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . (int) $sql->getValue('id') . '</td>
                            <td class="rex-table-article-name" data-title="' . I18n::msg('header_article_name') . '"><a class="rex-link-expanded" href="' . $editModeUrl . '">' . escape($sql->getValue('name')) . '</a></td>
                            ' . $tmplTd . '
                            <td class="rex-table-date" data-title="' . I18n::msg('header_date') . '">' . Formatter::intlDate($sql->getDateTimeValue('createdate')) . '</td>
                            <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '">' . escape($sql->getValue('priority')) . '</td>
                            ' . $addExtra . '
                        </tr>
                        ';
        } else {
            // --------------------- ARTIKEL NORMAL VIEW | NO EDIT NO ENTER

            $status = (int) $sql->getValue('status');
            $artStatus = $artStatusTypes[$status][0];
            $artStatusClass = $artStatusTypes[$status][1];
            $artStatusIcon = $artStatusTypes[$status][2];

            $tmpl = escape($templateNames[$sql->getValue('template_id')] ?? '');
            $tmplTd = '<td class="rex-table-template" data-title="' . I18n::msg('header_template') . '">
            <div class="rex-truncate rex-truncate-target" title="' . $tmpl . '" >' . $tmpl . '</div></td>';

            $echo .= '<tr ' . $dataArtStatus . ' ' . $dataArtid . ' class="' . $trStatusClass . '">
                            <td class="rex-table-icon"><i class="rex-icon' . $class . '"></i></td>
                            <td class="rex-table-id" data-title="' . I18n::msg('header_id') . '">' . (int) $sql->getValue('id') . '</td>
                            <td class="rex-table-article-name" data-title="' . I18n::msg('header_article_name') . '">' . escape($sql->getValue('name')) . '</td>
                            ' . $tmplTd . '
                            <td class="rex-table-date" data-title="' . I18n::msg('header_date') . '">' . Formatter::intlDate($sql->getDateTimeValue('createdate')) . '</td>
                            <td class="rex-table-priority" data-title="' . I18n::msg('header_priority') . '">' . escape($sql->getValue('priority')) . '</td>';
            if ($canEdit) {
                $echo .= '
                            <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</span></td>';
            }
            if ($canDelete) {
                $echo .= '
                            <td class="rex-table-action"><span class="text-muted"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</span></td>';
            }
            $echo .= '
                            <td class="rex-table-action"><span class="' . $artStatusClass . ' text-muted"><i class="rex-icon ' . $artStatusIcon . '"></i> ' . $artStatus . '</span></td>
                        </tr>';
        }

        $sql->next();
    }

    $echo .= '</tbody></table>';

    if ('add_art' == $structureContext->getFunction() || 'edit_art' == $structureContext->getFunction()) {
        $echo .= '
        </fieldset>
    </form>';
    }
}

$heading = I18n::msg('structure_articles_caption', $catName);
if (0 == $structureContext->getCategoryId()) {
    $heading = I18n::msg('structure_root_level_articles_caption');
}
$fragment = new Fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');
