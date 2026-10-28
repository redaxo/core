<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\Backend\Accesskey;
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
use Redaxo\Core\Content\ExtensionPoint\ArticleListOrder;
use Redaxo\Core\Content\ExtensionPoint\CategoryFormAdd;
use Redaxo\Core\Content\ExtensionPoint\CategoryFormButtons;
use Redaxo\Core\Content\ExtensionPoint\CategoryFormEdit;
use Redaxo\Core\Content\StructureContext;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Pagination;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Component\TableRow;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

// A failed category submit keeps the form open, showing the submitted values and their errors.
$categoryApi = ApiFunction::factory();
$categoryApiFailed = ($categoryApi instanceof CategoryAdd || $categoryApi instanceof CategoryEdit) && false === $categoryApi->result?->succeeded;

$structureContext = new StructureContext(
    categoryId: Request::request('category_id', 'int'),
    articleId: Request::request('article_id', 'int'),
    languageId: Request::request('language', 'int'),
    contentSectionId: Request::request('content_section_id', 'int'),
    artStart: Request::request('artstart', 'int'),
    catStart: Request::request('catstart', 'int'),
    editId: Request::request('edit_id', 'int'),
    function: match (true) {
        $categoryApiFailed && $categoryApi instanceof CategoryAdd => 'add_cat',
        $categoryApiFailed => 'edit_cat',
        default => Request::request('function', 'string'),
    },
);

$user = Core::requireUser();

if (0 === $structureContext->languageId) {
    if (Language::exists(0)) {
        echo Message::error('Oooops. Your language ids start with <code>0</code>. Looks like a broken REDAXO 4.x to 5.x upgrade. Please update all your database tables, php code (if there are any hard coded language ids) aswell as additional configurations in add-ons, e.g. YRewrite. You may start with updating those tables: <code>rex_article</code>, <code>rex_article_slice</code>, <code>rex_language</code>, by increasing every language id <code>+ 1</code>.');
        exit;
    }
    echo Message::error('You have no permission to access this area');
    exit;
}

// --------------------------------------------- TITLE
echo View::title(I18n::msg('title_structure'));

// --------------------------------------------- Languages
echo View::languageSwitchAsButtons($structureContext->getContext());

// --------------------------------------------- Path
$categoryId = $structureContext->categoryId;
$languageId = $structureContext->languageId;
echo View::structureBreadcrumb($categoryId, $structureContext->articleId, $languageId);

// -------------- STATUS_TYPE Map
$statusTypes = ArticleHandler::statusTypes();

// --------------------------------------------- API MESSAGES
echo ApiFunction::getMessage();

// --------------------------------------------- KATEGORIE LISTE
$catName = I18n::msg('root_level');
$category = Category::get($structureContext->categoryId, $structureContext->languageId);
if ($category) {
    $catName = $category->name;
}

$addCategory = '';
if ($user->hasPerm('addCategory[]') && $structureContext->hasCategoryPermission()) {
    $addCategory = '<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['function' => 'add_cat', 'catstart' => $structureContext->catStart]) . '"' . Accesskey::attributes(I18n::msg('add_category'), 'add') . '><i class="rex-icon rex-icon-add-category"></i></a>';
}

$dataColspan = 5;

// --------------------- COUNT CATEGORY ROWS

$KAT = Sql::factory();
// $KAT->setDebug();
if (count($structureContext->getMountpoints()) > 0 && 0 === $structureContext->categoryId) {
    $parentIds = $KAT->in($structureContext->getMountpoints());
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM rex_category WHERE id IN (' . $parentIds . ')');
} else {
    $KAT->setQuery('SELECT COUNT(*) as rowCount FROM rex_category c JOIN rex_article a ON a.id = c.id WHERE a.parent_id <=> ?', [$structureContext->categoryId ?: null]);
}

// --------------------- ADD PAGINATION

$catPager = new Pager(cursorName: 'catstart');
$catPager->setRowCount($KAT->getIntValue('rowCount'));
echo new Pagination($catPager, $structureContext->getContext())->render();

// --------------------- GET THE DATA

// the categories with their start articles, both in the current language
$categoryListQuery = '
    SELECT a.*, t.*, c.priority AS catpriority, ct.name AS catname
    FROM rex_category c
    JOIN rex_article a ON a.id = c.id
    JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = ?
    JOIN rex_category_translation ct ON ct.category_id = c.id AND ct.language_id = t.language_id
';

if (count($structureContext->getMountpoints()) > 0 && 0 === $structureContext->categoryId) {
    $parentIds = $KAT->in($structureContext->getMountpoints());

    $KAT->setQuery('SELECT parent_id FROM rex_article WHERE id IN (' . $parentIds . ') GROUP BY parent_id');
    $orderBy = $KAT->getRows() > 1 ? 'ct.name' : 'c.priority';

    $KAT->setQuery($categoryListQuery . ' WHERE a.id IN (' . $parentIds . ') ORDER BY ' . $orderBy . ' LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage(), [$structureContext->languageId]);
} else {
    $KAT->setQuery($categoryListQuery . ' WHERE a.parent_id <=> ? ORDER BY c.priority LIMIT ' . $catPager->getCursor() . ',' . $catPager->getRowsPerPage(), [$structureContext->languageId, $structureContext->categoryId ?: null]);
}

$trStatusClass = 'rex-status';
$echo = '';
// ---------- INLINE THE EDIT/ADD FORM
if ('add_cat' === $structureContext->function || 'edit_cat' === $structureContext->function) {
    $echo .= '
    <form action="' . $structureContext->getContext()->getUrl(['catstart' => $structureContext->catStart]) . '" method="post">
        <fieldset>

            <input type="hidden" name="edit_id" value="' . $structureContext->editId . '" />';
}

$categoryMetaToggle = static fn (MetaForm $meta): string => $meta->hasFields()
    ? '<button type="button" class="rex-button' . ($meta->hasErrors() ? '' : ' collapsed') . '" data-toggle="collapse" data-target="#rex-structure-category-metainfo" aria-controls="rex-structure-category-metainfo" aria-expanded="' . ($meta->hasErrors() ? 'true' : 'false') . '"><i class="rex-icon rex-icon-structure-category-metainfo" aria-hidden="true"></i><span class="sr-only">' . I18n::msg('metadata') . '</span></button>'
    : '';
$categoryMetaRow = static fn (MetaForm $meta): ?TableRow => $meta->hasFields()
    ? new TableRow([
        new TableCell(null, 2),
        new TableCell(Html::raw('<div class="rex-collapse-content">' . $meta->render() . '</div>'), 5),
    ], new HtmlAttributes(['id' => 'rex-structure-category-metainfo', 'class' => ['collapse', 'in' => $meta->hasErrors(), 'mark']]))
    : null;

$canEdit = $user->hasPerm('editCategory[]');
$canDelete = $user->hasPerm('deleteCategory[]');
$colspan = (int) $canEdit + (int) $canDelete + 1;
// the buttons of a form row span all action columns, starting with the first one
$firstAction = $canEdit ? 'edit' : ($canDelete ? 'delete' : 'status');
// one header for all action columns
$actionColumns = static fn (array $keys): array => array_values(array_map(
    static fn (string $key): TableColumn => TableColumn::forKey($key === $keys[0] ? I18n::msg('header_status') : null, $key, ColumnType::Action),
    $keys,
));
$muted = static fn (string $icon, string $label): Html => Html::raw('<span class="text-muted"><i class="rex-icon ' . $icon . '"></i> ' . $label . '</span>');

/** @var list<array<string, mixed>|TableRow|Html|null> $rows */
$rows = [];

// --------------------- KATEGORIE ADD FORM

if ('add_cat' === $structureContext->function && $user->hasPerm('addCategory[]') && $structureContext->hasCategoryPermission()) {
    $meta = new MetaForm(new MetaContext(MetaEntity::Category, category: Category::get($structureContext->categoryId, $structureContext->languageId)));
    if ($categoryApiFailed) {
        $meta->submit();
    }

    $metaButtons = $categoryMetaToggle($meta) . Extension::dispatch(new CategoryFormButtons(null, $category, $structureContext->languageId));
    $addButtons = CategoryAdd::getHiddenFields() . '
        <input type="hidden" name="parent-category-id" value="' . $structureContext->categoryId . '" />
        <button class="rex-button" data-variant="success" type="submit" name="category-add-button"' . Accesskey::attributes(I18n::msg('add_category'), 'save') . '>' . I18n::msg('add_category') . '</button>';

    $rows[] = [
        'attributes' => new HtmlAttributes(['class' => 'mark']),
        'icon' => Html::raw('<i class="rex-icon rex-icon-category"></i>'),
        'id' => '-',
        'name' => Html::raw('<input class="form-control" type="text" name="category-name" value="' . escape(Request::post('category-name', 'string')) . '" class="rex-js-autofocus" required maxlength="255" autofocus />'),
        'priority' => Html::raw('<input class="form-control" type="number" name="category-position" value="' . ($categoryApiFailed ? Request::post('category-position', 'int') : $catPager->getRowCount() + 1) . '" required min="1" inputmode="numeric" />'),
        $firstAction => new TableCell(Html::raw($metaButtons . $addButtons), $colspan),
    ];
    $rows[] = $categoryMetaRow($meta);

    // ----- EXTENSION POINT
    $rows[] = Html::raw(Extension::dispatch(new CategoryFormAdd($category, $structureContext->languageId, $dataColspan + 1)));
}

// --------------------- KATEGORIE LIST
for ($i = 0; $i < $KAT->getRows(); ++$i, $KAT->next()) {
    $iCategoryId = $KAT->getIntValue('id');

    $katLink = $structureContext->getContext()->getUrl(['category_id' => $iCategoryId]);

    /** @var Category $katObject */
    $katObject = Category::get($iCategoryId);
    $katHasChildElements = (count($katObject->getChildren()) > 0 || count($katObject->getArticles()) > 1); // contains child categories or articles other than the start article
    $katIconClass = $katHasChildElements ? 'rex-icon-category' : 'rex-icon-category-without-elements';
    $katIconTitle = $katHasChildElements ? I18n::msg('category_has_child_elements') : I18n::msg('category_without_child_elements');

    $status = $KAT->getIntValue('status');
    $katStatus = $statusTypes[$status][0];
    $statusClass = $statusTypes[$status][1];
    $statusIcon = $statusTypes[$status][2];

    $row = [
        'attributes' => new HtmlAttributes(['class' => $trStatusClass, 'data-status' => $status]),
        'icon' => Html::raw('<a class="rex-link-expanded" href="' . $katLink . '" title="' . escape($KAT->getStringValue('catname')) . '"><i class="rex-icon ' . $katIconClass . '" title="' . $katIconTitle . '"></i></a>'),
        'id' => $iCategoryId,
        'name' => Html::raw('<a class="rex-link-expanded" href="' . $katLink . '">' . escape($KAT->getStringValue('catname')) . '</a>'),
        'priority' => $KAT->getIntValue('catpriority'),
    ];

    if ($structureContext->hasCategoryPermission()) {
        $tdLayoutClass = '';
        if ($user->hasPerm('publishCategory[]')) {
            $tdLayoutClass = 'rex-table-action-no-dropdown';
            if (count($statusTypes) > 2) {
                $tdLayoutClass = 'rex-table-action-dropdown';
                $katStatus = '<div class="dropdown"><button type="button" class="rex-dropdown-link dropdown-toggle ' . $statusClass . '" data-toggle="dropdown" aria-expanded="false"><i class="rex-icon ' . $statusIcon . '" aria-hidden="true"></i>&nbsp;' . $katStatus . '&nbsp;<span class="caret" aria-hidden="true"></span></button><ul class="dropdown-menu dropdown-menu-right">';
                foreach ($statusTypes as $catStatusKey => $catStatusType) {
                    $katStatus .= '<li><a class="' . $catStatusType[1] . '" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->catStart, 'cat_status' => $catStatusKey] + CategoryStatusChange::getUrlParams()) . '">' . $catStatusType[0] . '</a></li>';
                }
                $katStatus .= '</ul></div>';
            } else {
                $katStatus = '<a class="rex-link-expanded ' . $statusClass . '" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->catStart] + CategoryStatusChange::getUrlParams()) . '"><i class="rex-icon ' . $statusIcon . '"></i>&nbsp;' . $katStatus . '</a>';
            }
        } else {
            $katStatus = '<span class="' . $statusClass . ' text-muted"><i class="rex-icon ' . $statusIcon . '"></i> ' . $katStatus . '</span>';
        }

        if ($canEdit && $structureContext->editId === $iCategoryId && 'edit_cat' === $structureContext->function) {
            // --------------------- KATEGORIE EDIT FORM

            $editCategory = Category::require($iCategoryId, $structureContext->languageId);
            $meta = new MetaForm(new MetaContext(MetaEntity::Category, $editCategory, $editCategory));
            if ($categoryApiFailed) {
                $meta->submit();
            }

            // ----- EXTENSION POINT
            $metaButtons = $categoryMetaToggle($meta) . Extension::dispatch(new CategoryFormButtons($editCategory, $category, $structureContext->languageId));

            $addButtons = CategoryEdit::getHiddenFields() . '
                <input type="hidden" name="category-id" value="' . $structureContext->editId . '" />
                <button class="rex-button" data-variant="success" type="submit" name="category-edit-button"' . Accesskey::attributes(I18n::msg('save_category'), 'save') . '>' . I18n::msg('save_category') . '</button>';

            $rows[] = [
                'attributes' => new HtmlAttributes(['class' => ['mark', 'rex-has-metainfo' => '' != $metaButtons], 'data-status' => $status]),
                'name' => Html::raw('<input class="form-control" type="text" name="category-name" value="' . escape($categoryApiFailed ? Request::post('category-name', 'string') : $KAT->getStringValue('catname')) . '" class="rex-js-autofocus" required maxlength="255" autofocus />'),
                'priority' => Html::raw('<input class="form-control" type="number" name="category-position" value="' . ($categoryApiFailed ? Request::post('category-position', 'int') : $KAT->getIntValue('catpriority')) . '" required min="1" inputmode="numeric" />'),
                $firstAction => new TableCell(Html::raw($metaButtons . $addButtons), $colspan),
            ] + $row;
            $rows[] = $categoryMetaRow($meta);

            // ----- EXTENSION POINT
            $rows[] = Html::raw(Extension::dispatch(new CategoryFormEdit($editCategory, $dataColspan + 1)));
        } else {
            // --------------------- KATEGORIE WITH WRITE

            $rows[] = $row + [
                'edit' => Html::raw('<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['edit_id' => $iCategoryId, 'function' => 'edit_cat', 'catstart' => $structureContext->catStart]) . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</a>'),
                'delete' => Html::raw('<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['category-id' => $iCategoryId, 'catstart' => $structureContext->catStart] + CategoryDelete::getUrlParams()) . '" data-confirm="' . I18n::msg('structure_delete_all_languages') . '"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a>'),
                'status' => new TableCell(Html::raw($katStatus), attributes: new HtmlAttributes(['class' => [$tdLayoutClass]])),
            ];
        }
    } elseif ($user->getComplexPerm('structure')->hasCategoryPerm($iCategoryId)) {
        // --------------------- KATEGORIE WITH READ

        $rows[] = $row + [
            'edit' => $muted('rex-icon-edit', I18n::msg('change')),
            'delete' => $muted('rex-icon-delete', I18n::msg('delete')),
            'status' => Html::raw('<span class="' . $statusClass . ' text-muted"><i class="rex-icon ' . $statusIcon . '"></i> ' . $katStatus . '</span>'),
        ];
    }
}

if (0 === $KAT->getRows()) {
    $rows[] = new TableRow([new TableCell(Html::raw('&nbsp;')), new TableCell(null), new TableCell(null), new TableCell(null), new TableCell(null, $colspan)]);
}

$echo .= (string) new Table(
    [
        TableColumn::forKey(Html::raw($addCategory), 'icon', ColumnType::Icon),
        TableColumn::forKey(I18n::msg('header_id'), 'id', ColumnType::Id),
        TableColumn::forKey(I18n::msg('header_category'), 'name', attributes: new HtmlAttributes(['class' => ['rex-table-category']])),
        TableColumn::forKey(I18n::msg('header_priority'), 'priority', ColumnType::Priority),
        ...$actionColumns(array_keys(array_filter(['edit' => $canEdit, 'delete' => $canDelete, 'status' => true]))),
    ],
    array_filter($rows),
    rowAttributes: static fn (array $row): HtmlAttributes => Type::instanceOf($row['attributes'], HtmlAttributes::class),
    attributes: new HtmlAttributes(['class' => ['table-striped', 'table-hover']]),
)->render();

if ('add_cat' === $structureContext->function || 'edit_cat' === $structureContext->function) {
    $echo .= '
    </fieldset>
</form>';
}

$heading = I18n::msg('structure_categories_caption', $catName);
if (0 === $structureContext->categoryId) {
    $heading = I18n::msg('structure_root_level_categories_caption');
}
$fragment = new Fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');

// --------------------------------------------- ARTIKEL LISTE

$echo = '';

// --------------------- READ TEMPLATES

$templates = Template::getTemplatesForCategory($structureContext->categoryId);
$templateChoices = array_map(static fn (Template $template) => I18n::translate($template->name), $templates);
new Collator(Locale::getDefault())->asort($templateChoices);
/** @var array<string, string> $templateChoices */
$templateChoices = $templateChoices ?: ['' => I18n::msg('option_no_template')];

if ($structureContext->categoryId > 0 || (0 === $structureContext->categoryId && !$user->getComplexPerm('structure')->hasMountpoints())) {
    // --------------------- ARTIKEL LIST
    $artAddLink = '';
    if ($user->hasPerm('addArticle[]') && $structureContext->hasCategoryPermission()) {
        $artAddLink = '<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['function' => 'add_art', 'artstart' => $structureContext->artStart]) . '"' . Accesskey::attributes(I18n::msg('article_add'), 'add_2') . '><i class="rex-icon rex-icon-add-article"></i></a>';
    }

    $articleOrderBy = Extension::dispatch(new ArticleListOrder($category, $structureContext->languageId));

    // ---------- COUNT DATA
    $sql = Sql::factory();
    // $sql->setDebug();
    $sql->setQuery('
        SELECT COUNT(*) as artCount
        FROM rex_article a
        LEFT JOIN rex_category c ON c.id = a.id
        WHERE
            (a.parent_id <=> :category_id AND c.id IS NULL) OR (a.id = :category_id AND c.id IS NOT NULL)
    ', [
        'category_id' => $structureContext->categoryId ?: null,
    ]);

    // --------------------- ADD PAGINATION

    $artPager = new Pager(cursorName: 'artstart');
    $artPager->setRowCount($sql->getIntValue('artCount'));
    echo new Pagination($artPager, $structureContext->getContext())->render();

    // ---------- READ DATA
    $sql->setQuery('
        SELECT a.*, t.*, c.id IS NOT NULL AS startarticle
        FROM rex_article a
        JOIN rex_article_translation t ON t.article_id = a.id AND t.language_id = :language_id
        LEFT JOIN rex_category c ON c.id = a.id
        WHERE
            (a.parent_id <=> :category_id AND c.id IS NULL) OR (a.id = :category_id AND c.id IS NOT NULL)
        ORDER BY
            ' . $articleOrderBy . '
        LIMIT ' . $artPager->getCursor() . ',' . $artPager->getRowsPerPage(),
        [
            'category_id' => $structureContext->categoryId ?: null,
            'language_id' => $structureContext->languageId,
        ],
    );

    // ---------- INLINE THE EDIT/ADD FORM
    if ('add_art' === $structureContext->function || 'edit_art' === $structureContext->function) {
        $echo .= '
        <form action="' . $structureContext->getContext()->getUrl(['artstart' => $structureContext->artStart]) . '" method="post">
            <fieldset>';
    }

    $canEdit = $user->hasPerm('editArticle[]');
    $canDelete = $user->hasPerm('deleteArticle[]');
    $colspan = (int) $canEdit + (int) $canDelete + 1;
    $firstAction = $canEdit ? 'edit' : ($canDelete ? 'delete' : 'status');
    $templateCell = static fn (string $template): Html => Html::raw('<div class="rex-truncate rex-truncate-target" title="' . $template . '" >' . $template . '</div>');

    $rows = [];

    // --------------------- ARTIKEL ADD FORM
    if ('add_art' === $structureContext->function && $user->hasPerm('addArticle[]') && $structureContext->hasCategoryPermission()) {
        // new articles inherit the template of the start article
        $template = Article::get($structureContext->categoryId, $structureContext->languageId)?->templateKey;
        $template = isset($templates[$template ?? '']) ? $template : Template::getDefaultKey();

        $rows[] = [
            'attributes' => new HtmlAttributes(['class' => 'mark']),
            'icon' => Html::raw('<i class="rex-icon rex-icon-article"></i>'),
            'id' => '-',
            'name' => Html::raw('<input class="form-control" type="text" name="article-name" required maxlength="255" autofocus />'),
            'template' => new SelectInput('template', $templateChoices, $template),
            'date' => Formatter::intlDate(time()),
            'priority' => Html::raw('<input class="form-control" type="number" name="article-position" value="' . ($artPager->getRowCount() + 1) . '" required min="1" inputmode="numeric" />'),
            $firstAction => new TableCell(Html::raw(ArticleAdd::getHiddenFields() . '<button class="rex-button" data-variant="success" type="submit" name="artadd_function"' . Accesskey::attributes(I18n::msg('article_add'), 'save') . '>' . I18n::msg('article_add') . '</button>'), $colspan),
        ];
    } elseif (0 === $sql->getRows()) {
        $rows[] = new TableRow([new TableCell(Html::raw('&nbsp;')), new TableCell(null), new TableCell(null), new TableCell(null), new TableCell(null), new TableCell(null), new TableCell(null, $colspan)]);
    }

    // --------------------- ARTIKEL LIST
    for ($i = 0; $i < $sql->getRows(); ++$i, $sql->next()) {
        $articleId = $sql->getIntValue('id');
        if ($articleId === Article::getSiteStartArticleId()) {
            $class = ' rex-icon-sitestartarticle';
        } elseif ($sql->getBoolValue('startarticle')) {
            $class = ' rex-icon-startarticle';
        } else {
            $class = ' rex-icon-article';
        }

        $status = $sql->getIntValue('status');
        $tmplKey = ($sql->getNullableStringValue('template') ?? '');
        $tmpl = escape(null !== ($t = Template::get($tmplKey)) ? I18n::translate($t->name) : '');

        $row = [
            'id' => $articleId,
            'template' => $templateCell($tmpl),
            'date' => Formatter::intlDate($sql->getDateTimeValue('createdate')),
            'priority' => $sql->getIntValue('priority'),
        ];

        // --------------------- ARTIKEL EDIT FORM

        if ($canEdit && 'edit_art' === $structureContext->function && $articleId === $structureContext->articleId && $structureContext->hasCategoryPermission()) {
            $rows[] = [
                'attributes' => new HtmlAttributes(['class' => ['mark', 'rex-startarticle' => $sql->getBoolValue('startarticle'), $trStatusClass]]),
                'icon' => Html::raw('<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $articleId]) . '" title="' . escape($sql->getStringValue('name')) . '"><i class="rex-icon' . $class . '"></i></a>'),
                'name' => Html::raw('<input class="form-control" type="text" name="article-name" value="' . escape($sql->getStringValue('name')) . '" required maxlength="255" autofocus />'),
                'template' => new SelectInput('template', $templateChoices, $sql->getNullableStringValue('template')),
                'priority' => Html::raw('<input class="form-control" type="number" name="article-position" value="' . $sql->getIntValue('priority') . '" required min="1" inputmode="numeric" />'),
                $firstAction => new TableCell(Html::raw(ArticleEdit::getHiddenFields() . '<button class="rex-button" data-variant="success" type="submit" name="artedit_function"' . Accesskey::attributes(I18n::msg('article_save'), 'save') . '>' . I18n::msg('article_save') . '</button>'), $colspan),
            ] + $row;
        } elseif ($structureContext->hasCategoryPermission()) {
            // --------------------- ARTIKEL NORMAL VIEW | EDIT AND ENTER

            $articleStatus = $statusTypes[$status][0];
            $articleClass = $statusTypes[$status][1];
            $articleIcon = $statusTypes[$status][2];

            if ($sql->getBoolValue('startarticle')) {
                // the start article is deleted and switched with its category
                $delete = $muted('rex-icon-delete', I18n::msg('delete'));
                $statusCell = new TableCell(Html::raw('<span class="' . $articleClass . ' text-muted"><i class="rex-icon ' . $articleIcon . '"></i> ' . $articleStatus . '</span>'));
            } else {
                $delete = Html::raw('<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['article_id' => $articleId, 'artstart' => $structureContext->artStart] + ArticleDelete::getUrlParams()) . '" data-confirm="' . I18n::msg('structure_delete_all_languages') . '"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a>');

                $tdLayoutClass = '';
                if ($user->hasPerm('publishArticle[]')) {
                    $tdLayoutClass = 'rex-table-action-no-dropdown';

                    if (count($statusTypes) > 2) {
                        $tdLayoutClass = 'rex-table-action-dropdown';
                        $articleStatus = '<div class="dropdown"><button type="button" class="rex-dropdown-link dropdown-toggle ' . $articleClass . '" data-toggle="dropdown" aria-expanded="false"><i class="rex-icon ' . $articleIcon . '" aria-hidden="true"></i>&nbsp;' . $articleStatus . '&nbsp;<span class="caret" aria-hidden="true"></span></button><ul class="dropdown-menu dropdown-menu-right">';
                        foreach ($statusTypes as $artStatusKey => $artStatusType) {
                            $articleStatus .= '<li><a  class="' . $artStatusType[1] . '" href="' . $structureContext->getContext()->getUrl(['article_id' => $articleId, 'artstart' => $structureContext->artStart, 'art_status' => $artStatusKey] + ArticleStatusChange::getUrlParams()) . '">' . $artStatusType[0] . '</a></li>';
                        }
                        $articleStatus .= '</ul></div>';
                    } else {
                        $articleStatus = '<a class="' . $articleClass . '" href="' . $structureContext->getContext()->getUrl(['article_id' => $articleId, 'artstart' => $structureContext->artStart] + ArticleStatusChange::getUrlParams()) . '"><i class="rex-icon ' . $articleIcon . '"></i>&nbsp;' . $articleStatus . '</a>';
                    }
                } else {
                    $articleStatus = '<span class="' . $articleClass . ' text-muted"><i class="rex-icon ' . $articleIcon . '"></i> ' . $articleStatus . '</span>';
                }

                $statusCell = new TableCell(Html::raw($articleStatus), attributes: new HtmlAttributes(['class' => [$tdLayoutClass]]));
            }

            $editModeUrl = $structureContext->getContext()->getUrl(['page' => 'content/edit', 'article_id' => $articleId, 'mode' => 'edit']);

            $rows[] = $row + [
                'attributes' => new HtmlAttributes(['data-status' => $status, 'data-article-id' => $articleId, 'class' => ['rex-startarticle' => $sql->getBoolValue('startarticle'), $trStatusClass]]),
                'icon' => Html::raw('<a class="rex-link-expanded" href="' . $editModeUrl . '" title="' . escape($sql->getStringValue('name')) . '"><i class="rex-icon' . $class . '"></i></a>'),
                'name' => Html::raw('<a class="rex-link-expanded" href="' . $editModeUrl . '">' . escape($sql->getStringValue('name')) . '</a>'),
                'edit' => Html::raw('<a class="rex-link-expanded" href="' . $structureContext->getContext()->getUrl(['article_id' => $articleId, 'function' => 'edit_art', 'artstart' => $structureContext->artStart]) . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</a>'),
                'delete' => $delete,
                'status' => $statusCell,
            ];
        } else {
            // --------------------- ARTIKEL NORMAL VIEW | NO EDIT NO ENTER

            $rows[] = $row + [
                'attributes' => new HtmlAttributes(['data-status' => $status, 'data-article-id' => $articleId, 'class' => $trStatusClass]),
                'icon' => Html::raw('<i class="rex-icon' . $class . '"></i>'),
                'name' => $sql->getStringValue('name'),
                'edit' => $muted('rex-icon-edit', I18n::msg('change')),
                'delete' => $muted('rex-icon-delete', I18n::msg('delete')),
                'status' => Html::raw('<span class="' . $statusTypes[$status][1] . ' text-muted"><i class="rex-icon ' . $statusTypes[$status][2] . '"></i> ' . $statusTypes[$status][0] . '</span>'),
            ];
        }
    }

    $echo .= (string) new Table(
        [
            TableColumn::forKey(Html::raw($artAddLink), 'icon', ColumnType::Icon),
            TableColumn::forKey(I18n::msg('header_id'), 'id', ColumnType::Id),
            TableColumn::forKey(I18n::msg('header_article_name'), 'name', attributes: new HtmlAttributes(['class' => ['rex-table-article-name']])),
            TableColumn::forKey(I18n::msg('header_template'), 'template', attributes: new HtmlAttributes(['class' => ['rex-table-template']])),
            TableColumn::forKey(I18n::msg('header_date'), 'date', ColumnType::Date),
            TableColumn::forKey(I18n::msg('header_priority'), 'priority', ColumnType::Priority),
            ...$actionColumns(array_keys(array_filter(['edit' => $canEdit, 'delete' => $canDelete, 'status' => true]))),
        ],
        $rows,
        rowAttributes: static fn (array $row): HtmlAttributes => Type::instanceOf($row['attributes'], HtmlAttributes::class),
        attributes: new HtmlAttributes(['class' => ['table-striped', 'table-hover']]),
    )->render();

    if ('add_art' === $structureContext->function || 'edit_art' === $structureContext->function) {
        $echo .= '
        </fieldset>
    </form>';
    }
}

$heading = I18n::msg('structure_articles_caption', $catName);
if (0 === $structureContext->categoryId) {
    $heading = I18n::msg('structure_root_level_articles_caption');
}
$fragment = new Fragment();
$fragment->setVar('heading', $heading, false);
$fragment->setVar('content', $echo, false);
echo $fragment->parse('core/page/section.php');
