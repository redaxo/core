<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

assert(isset($PERMALL) && is_bool($PERMALL));
assert(isset($argFields) && is_string($argFields));
assert(isset($argUrl) && is_array($argUrl));

// defaults for globals passed in from index.php
if (!isset($success)) {
    $success = '';
}
if (!isset($error)) {
    $error = '';
}

// *************************************** SUBPAGE: KATEGORIEN

$mediaMethod = Request::request('media_method', 'string');
$csrf = CsrfToken::factory('mediapool_structure');

if ($PERMALL) {
    $editId = Request::request('edit_id', 'int');

    if (in_array($mediaMethod, ['edit_file_cat', 'delete_file_cat', 'add_file_cat'])) {
        if (!$csrf->isValid()) {
            $error = I18n::msg('csrf_token_invalid');
        } else {
            try {
                if ('edit_file_cat' == $mediaMethod) {
                    $category = MediaCategoryHandler::edit($editId, ['name' => Request::request('cat_name', 'string')]);
                    $success = I18n::msg('pool_kat_updated', $category->name);
                } elseif ('delete_file_cat' == $mediaMethod) {
                    MediaCategoryHandler::delete($editId);
                    $success = I18n::msg('pool_kat_deleted');
                } elseif ('add_file_cat' == $mediaMethod) {
                    $category = MediaCategoryHandler::add([
                        'parent_id' => Request::request('cat_id', 'int') ?: null,
                        'name' => Request::request('catname', 'string'),
                    ]);
                    $success = I18n::msg('pool_kat_saved', $category->name);
                }
            } catch (UserMessageException $e) {
                $error = $e->getMessage();
            }
        }
    }

    $link = Url::currentBackendPage(array_merge($argUrl, ['cat_id' => '']));

    $breadcrumb = [];

    $n = [];
    $n['title'] = I18n::msg('pool_kat_start');
    $n['href'] = $link . '0';
    $breadcrumb[] = $n;

    $catId = Request::request('cat_id', 'int');
    $category = $catId ? MediaCategory::get($catId) : null;
    if (!$category) {
        $OOCats = MediaCategory::getRootCategories();
        $catId = 0;
    } else {
        $OOCats = $category->getChildren();

        foreach ($category->getParentTree() as $parent) {
            $n = [];
            $n['title'] = escape($parent->name);
            $n['href'] = $link . $parent->id;
            $breadcrumb[] = $n;
        }
        $n = [];
        $n['title'] = escape($category->name);
        $n['href'] = $link . $catId;
        $breadcrumb[] = $n;
    }

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('pool_kat_path'), false);
    $fragment->setVar('items', $breadcrumb, false);
    echo $fragment->parse('core/navigations/breadcrumb.php');

    if ('' != $error) {
        echo Message::error($error);
        $error = '';
    }
    if ('' != $success) {
        echo Message::info($success);
        $success = '';
    }

    // the category being added or edited is a form
    $form = static fn (?MediaCategory $category): bool => null === $category ? 'add_cat' == $mediaMethod : 'update_file_cat' == $mediaMethod && $editId == $category->id;

    $table = (string) new Table([
        new TableColumn(
            Html::raw('<a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=add_cat"' . Accesskey::attributes(I18n::msg('pool_kat_create'), 'add') . '><i class="rex-icon rex-icon-add-media-category"></i></a>'),
            static fn (?MediaCategory $category): Html => null === $category || $form($category)
                ? Html::raw('<i class="rex-icon rex-icon-media-category"></i>')
                : Html::raw('<a class="rex-link-expanded" href="' . $link . $category->id . '" title="' . escape($category->name) . '"><i class="rex-icon rex-icon-media-category"></i></a>'),
            ColumnType::Icon,
        ),
        new TableColumn(I18n::msg('id'), static fn (?MediaCategory $category): int|string => $category->id ?? '-', ColumnType::Id),
        new TableColumn(I18n::msg('pool_kat_name'), static fn (?MediaCategory $category): Html => match (true) {
            null === $category => Html::raw('<input class="form-control" type="text" name="catname" value="" autofocus />'),
            $form($category) => Html::raw('<input class="form-control" type="text" name="cat_name" value="' . escape($category->name) . '" autofocus />'),
            default => Html::raw('<a class="rex-link-expanded" href="' . $link . $category->id . '">' . escape($category->name) . '</a>'),
        }),
        new TableColumn(I18n::msg('pool_kat_function'), static function (?MediaCategory $category) use ($form, $link, $catId, $editId): Html|TableCell {
            if (null === $category) {
                return new TableCell(Html::raw('<button class="rex-button" data-variant="success" type="submit" value="' . I18n::msg('pool_kat_create') . '"' . Accesskey::attributes(I18n::msg('pool_kat_create'), 'save') . '>' . I18n::msg('pool_kat_create') . '</button>'), 2);
            }
            if ($form($category)) {
                return new TableCell(Html::raw('<input type="hidden" name="edit_id" value="' . $editId . '" />
                        <button class="rex-button" data-variant="success" type="submit" value="' . I18n::msg('pool_kat_update') . '"' . Accesskey::attributes(I18n::msg('pool_kat_update'), 'save') . '>' . I18n::msg('pool_kat_update') . '</button>'), 2);
            }
            return Html::raw('<a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=update_file_cat&amp;edit_id=' . $category->id . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('pool_kat_edit') . '</a>');
        }, ColumnType::Action),
        new TableColumn(null, static fn (MediaCategory $category): Html => Html::raw('<a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=delete_file_cat&amp;edit_id=' . $category->id . '&amp;' . http_build_query($csrf->getUrlParams()) . '" data-confirm="' . I18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('pool_kat_delete') . '</a>'), ColumnType::Action),
    ], [...('add_cat' == $mediaMethod ? [null] : []), ...$OOCats],
        rowAttributes: static fn (?MediaCategory $category): HtmlAttributes => new HtmlAttributes(['class' => ['mark' => null === $category || $form($category)]]),
        attributes: new HtmlAttributes(['class' => ['table-striped', 'table-hover']]),
    )->render();

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('pool_kat_caption'), false);
    $fragment->setVar('content', $table, false);
    $content = $fragment->parse('core/page/section.php');

    if ('add_cat' == $mediaMethod || 'update_file_cat' == $mediaMethod) {
        $addMode = 'add_cat' == $mediaMethod;
        $method = $addMode ? 'add_file_cat' : 'edit_file_cat';

        $content = '
            <form action="' . Url::currentBackendPage() . '" method="post">
                ' . $csrf->getHiddenField() . '
                <fieldset>
                    <input type="hidden" name="media_method" value="' . $method . '" />
                    <input type="hidden" name="cat_id" value="' . $catId . '" />
                    ' . $argFields . '
                    ' . $content . '
                </fieldset>
            </form>
        </div>
        ';
    }

    echo $content;
}
