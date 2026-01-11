<?php

use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
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
            if ('edit_file_cat' == $mediaMethod) {
                $catName = Request::request('cat_name', 'string');
                $data = ['name' => $catName];
                $success = MediaCategoryHandler::editCategory($editId, $data);
            } elseif ('delete_file_cat' == $mediaMethod) {
                try {
                    $success = MediaCategoryHandler::deleteCategory($editId);
                } catch (UserMessageException $e) {
                    $error = $e->getMessage();
                }
            } elseif ('add_file_cat' == $mediaMethod) {
                $parent = null;
                $parentId = Request::request('cat_id', 'int');
                if ($parentId) {
                    $parent = MediaCategory::get($parentId);
                }
                $success = MediaCategoryHandler::addCategory(
                    Request::request('catname', 'string'),
                    $parent,
                );
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
    if (0 == $catId || !($OOCat = MediaCategory::get($catId))) {
        $OOCats = MediaCategory::getRootCategories();
        $catId = 0;
        $catpath = '|';
    } else {
        $OOCats = $OOCat->getChildren();
        $paths = explode('|', $OOCat->getPath());

        for ($i = 1; $i < count($paths); ++$i) {
            $iid = current($paths);
            if ('' != $iid) {
                $icat = MediaCategory::get((int) $iid);

                $n = [];
                $n['title'] = escape($icat->getName());
                $n['href'] = $link . $iid;
                $breadcrumb[] = $n;
            }
            next($paths);
        }
        $n = [];
        $n['title'] = escape($OOCat->getName());
        $n['href'] = $link . $catId;
        $breadcrumb[] = $n;
        $catpath = $OOCat->getPath() . "$catId|";
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

    $table = '
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th class="rex-table-icon"><a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=add_cat"' . Core::getAccesskey(I18n::msg('pool_kat_create'), 'add') . ' title="' . I18n::msg('pool_kat_create') . '"><i class="rex-icon rex-icon-add-media-category"></i></a></th>
                    <th class="rex-table-id">' . I18n::msg('id') . '</th>
                    <th>' . I18n::msg('pool_kat_name') . '</th>
                    <th class="rex-table-action" colspan="2">' . I18n::msg('pool_kat_function') . '</th>
                </tr>
            </thead>
            <tbody>';

    if ('add_cat' == $mediaMethod) {
        $table .= '
            <tr class="mark">
                <td class="rex-table-icon"><i class="rex-icon rex-icon-media-category"></i></td>
                <td class="rex-table-id" data-title="' . I18n::msg('id') . '">-</td>
                <td data-title="' . I18n::msg('pool_kat_name') . '"><input class="form-control" type="text" name="catname" value="" autofocus /></td>
                <td class="rex-table-action" colspan="2">
                    <button class="btn btn-save" type="submit" value="' . I18n::msg('pool_kat_create') . '"' . Core::getAccesskey(I18n::msg('pool_kat_create'), 'save') . '>' . I18n::msg('pool_kat_create') . '</button>
                </td>
            </tr>
        ';
    }

    foreach ($OOCats as $OOCat) {
        $iid = $OOCat->getId();
        $iname = $OOCat->getName();

        if ('update_file_cat' == $mediaMethod && $editId == $iid) {
            $table .= '
                <tr class="mark">
                    <td class="rex-table-icon"><i class="rex-icon rex-icon-media-category"></i></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . $iid . '</td>
                    <td data-title="' . I18n::msg('pool_kat_name') . '"><input class="form-control" type="text" name="cat_name" value="' . escape($iname) . '" autofocus /></td>
                    <td class="rex-table-action" colspan="2">
                        <input type="hidden" name="edit_id" value="' . $editId . '" />
                        <button class="btn btn-save" type="submit" value="' . I18n::msg('pool_kat_update') . '"' . Core::getAccesskey(I18n::msg('pool_kat_update'), 'save') . '>' . I18n::msg('pool_kat_update') . '</button>
                    </td>
                </tr>
            ';
        } else {
            $table .= '
                <tr>
                    <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $link . $iid . '" title="' . escape($OOCat->getName()) . '"><i class="rex-icon rex-icon-media-category"></i></a></td>
                    <td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . $iid . '</td>
                    <td data-title="' . I18n::msg('pool_kat_name') . '"><a class="rex-link-expanded" href="' . $link . $iid . '">' . escape($OOCat->getName()) . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=update_file_cat&amp;edit_id=' . $iid . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('pool_kat_edit') . '</a></td>
                    <td class="rex-table-action"><a class="rex-link-expanded" href="' . $link . $catId . '&amp;media_method=delete_file_cat&amp;edit_id=' . $iid . '&amp;' . http_build_query($csrf->getUrlParams()) . '" data-confirm="' . I18n::msg('delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('pool_kat_delete') . '</a></td>
                </tr>';
        }
    }
    $table .= '
            </tbody>
        </table>';

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
                    <input type="hidden" name="catpath" value="' . $catpath . '" />
                    ' . $argFields . '
                    ' . $content . '
                </fieldset>
            </form>
        </div>
        ';
    }

    echo $content;
}
