<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaListFunctions;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaListThumbnail;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaCategoryChoices;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonVariant;
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

use function Redaxo\Core\View\escape;

assert(isset($rexFileCategory) && is_int($rexFileCategory));
assert(isset($picker) && is_string($picker));
assert(isset($argFields) && is_string($argFields));
assert(isset($toolbar) && is_string($toolbar));
assert(isset($rexFileCategoryName) && is_string($rexFileCategoryName));
/** @psalm-suppress RedundantCondition */
assert(isset($csrf) && $csrf instanceof CsrfToken);

// defaults for globals passed in from index.php
if (!isset($success)) {
    $success = '';
}
if (!isset($error)) {
    $error = '';
}

/** @var array{types?: string, picker?: string, language?: int} $argUrl */
$argUrl ??= [];

$mediaMethod = Request::request('media_method', 'string');

$perm = Core::requireUser()->getComplexPerm('media');
$hasCategoryPerm = $perm->hasCategoryPerm($rexFileCategory);

if ($hasCategoryPerm && 'updatecat_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = Request::post('selectedmedia', 'array');
        if (isset($selectedmedia[0]) && '' != $selectedmedia[0]) {
            $error = [];
            $success = [];

            $countMoved = 0;
            foreach ($selectedmedia as $fileName) {
                $fileName = (string) $fileName;
                $media = Media::get($fileName);

                if (!$media) {
                    $error[] = I18n::msg('pool_file_not_found', $fileName);

                    continue;
                }

                // the target category is already covered by $hasCategoryPerm
                if (!$perm->hasCategoryPerm($media->categoryId)) {
                    $error[] = I18n::msg('no_rights_to_this_function');

                    continue;
                }

                try {
                    MediaHandler::edit($fileName, Language::getCurrentId(), ['category_id' => $rexFileCategory ?: null]);
                    ++$countMoved;
                } catch (UserMessageException $e) {
                    $error[] = $e->getMessage();
                }
            }

            if ($countMoved) {
                $success[] = I18n::msg('pool_selectedmedia_moved');
            }
        } else {
            $error = I18n::msg('pool_selectedmedia_error');
        }
    }
}

if ($hasCategoryPerm && 'delete_selectedmedia' == $mediaMethod) {
    if (!$csrf->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $selectedmedia = Request::post('selectedmedia', 'array');
        if (0 != count($selectedmedia)) {
            $error = [];
            $success = [];

            $countDeleted = 0;
            foreach ($selectedmedia as $filename) {
                $media = Media::get($filename);
                if ($media) {
                    if ($perm->hasCategoryPerm($media->categoryId)) {
                        try {
                            MediaHandler::delete($filename);
                            ++$countDeleted;
                        } catch (UserMessageException $e) {
                            $error[] = $e->getMessage();
                        }
                    } else {
                        $error[] = I18n::msg('no_rights_to_this_function');
                    }
                } else {
                    $error[] = I18n::msg('pool_file_not_found', $filename);
                }
            }
            if ($countDeleted) {
                $success[] = I18n::msg('pool_files_deleted', $countDeleted);
            }
        } else {
            $error = I18n::msg('pool_selectedmedia_error');
        }
    }
}

$catsSel = new SelectInput(
    'rex_file_category',
    new MediaCategoryChoices(root: true)->choices(),
    (string) $rexFileCategory,
    'rex_file_category',
    search: true,
);

if ($error) {
    if (is_array($error)) {
        $error = implode('<br />', $error);
    }

    echo Message::error($error);
    $error = '';
}

if ($success) {
    if (is_array($success)) {
        $success = implode('<br />', $success);
    }

    echo Message::success($success);
    $success = '';
}

if (!empty($argUrl['types'])) {
    echo Message::info(I18n::msg('pool_file_filter') . ' <code>' . escape($argUrl['types']) . '</code>');
}

// Add Filter to the Query
$filter = [];

$mediaName = Request::request('media_name', 'string');
if ('' != $mediaName) {
    $filter['term'] = $mediaName;

    if (0 != $rexFileCategory) {
        $filter['category_id_path'] = $rexFileCategory;
    }
} else {
    $filter['category_id'] = $rexFileCategory ?: null;
}

if (isset($argUrl['types'])) {
    $filter['types'] = explode(',', $argUrl['types']);
}

$context = new Context([
    'page' => Controller::getCurrentPage(),
    'rex_file_category' => $rexFileCategory,
    'media_name' => $mediaName,
    ...$argUrl,
]);
$pager = new Pager();

$items = MediaHandler::getList($filter, [], $pager);

$panel = (string) new Pagination($pager, $context)->render();

// deletefilelist und cat change
$panel .= '
<form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
    <fieldset>
        ' . $csrf->getHiddenField() . '
        ' . $argFields;

// ----- move, delete and get selected items
$footer = '';
if ($hasCategoryPerm) {
    $addInput = '';
    $filecat = Sql::factory();
    $filecat->setQuery('SELECT * FROM rex_media_category ORDER BY name ASC LIMIT 1');

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_select_all') . '</label>';
    $e['field'] = '<input type="checkbox" name="checkie" value="0" data-rex-check-all="selectedmedia[]" />';
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $checkbox = $fragment->parse('core/form/checkbox.php');

    $field = '';
    if ($filecat->getRows() > 0) {
        $e = [];
        $e['field'] = $catsSel->render();
        $e['left'] = I18n::msg('pool_changecat_selectedmedia_prefix');
        $e['right'] = '<button class="rex-button" data-variant="primary" type="submit" name="media_method" value="updatecat_selectedmedia">' . I18n::msg('pool_changecat_selectedmedia_suffix') . '</button>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);
        $field .= '<div class="rex-truncate-dropdown">' . $fragment->parse('core/form/input_group.php') . '</div>';
    }

    $buttons = [
        new Button(I18n::msg('pool_delete_selectedmedia'), variant: ButtonVariant::Danger, type: 'submit', attributes: new HtmlAttributes([
            'data-confirm' => I18n::msg('delete') . ' ?',
            'name' => 'media_method',
            'value' => 'delete_selectedmedia',
        ])),
    ];
    if ('multiple' === $picker) {
        $buttons[] = new Button(I18n::msg('pool_get_selectedmedia'), variant: ButtonVariant::Success, attributes: new HtmlAttributes(['data-rex-pick-checked' => 'selectedmedia[]']));
    }

    $actionButtons = implode(' ', array_map(static fn (Button $button): string => (string) $button->render(), $buttons));

    $field = '<div class="rex-grid" data-from="sm" data-columns="7 5"><div>' . $field . '</div><div data-align="end">' . $actionButtons . '</div></div>';

    $e = [];
    $e['label'] = '<label>' . I18n::msg('pool_selectedmedia') . '</label>';
    $e['field'] = $field;
    $e['class'] = 'rex-form-group-no-margin';
    $fragment = new Fragment();
    $fragment->setVar('elements', [$e], false);
    $field = $fragment->parse('core/form/form.php');

    $footer = '
                <tr>
                    <td colspan="2">
                        ' . $checkbox . '
                    </td>
                    <td colspan="4">
                        ' . $field . '
                    </td>
                </tr>';
}

$rows = [];
foreach ($items as $media) {
    $alt = escape($media->title);
    $desc = '<p>' . escape(strip_tags((string) $media->getValue('description'))) . '</p>';

    if (!is_file(Path::media($media->fileName))) {
        $thumbnail = '<i class="rex-mime rex-mime-error" title="' . I18n::msg('pool_file_does_not_exist') . '"></i><span class="rex-u-visually-hidden">' . $media->fileName . '</span>';
    } else {
        $fileExt = File::extension($media->fileName);
        $thumbnail = '<i class="rex-mime rex-mime-' . escape($fileExt) . '" title="' . $alt . '" data-extension="' . $fileExt . '"></i><span class="rex-u-visually-hidden">' . $media->fileName . '</span>';

        if ('svg' === $fileExt) {
            // SVG is rendered by the browser directly, no image driver involved
            $thumbnail = '<img class="rex-thumbnail" src="' . Url::media($media->fileName) . '?buster=' . $media->getValue('updatedate') . '" width="80" height="80" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
        } elseif (MediaManager::canPreview($fileExt)) {
            // a rasterised preview can be produced (image formats always, plus e.g. PDF when the driver supports it)
            $thumbnail = '<img class="rex-thumbnail" src="' . MediaManager::getUrl('rex_media_small', urlencode($media->fileName), $media->getValue('updatedate')) . '" width="100" alt="' . $alt . '" title="' . $alt . '" loading="lazy" />';
        }
    }

    $thumbnail = Extension::dispatch(new MediaListThumbnail($media, $thumbnail));

    if ('' == $media->title) {
        $fileTitle = '[' . I18n::msg('pool_file_notitle') . ']';
    }

    $openerLink = '';
    if ('' !== $picker) {
        $openerLink = '<button ' . new HtmlAttributes([
            'type' => 'button',
            'class' => ['rex-button'],
            'data-variant' => 'primary',
            'data-size' => 'xs',
            'data-effect' => 'multiple' === $picker ? 'flash' : null,
            'data-rex-pick' => $media->fileName,
            'data-rex-pick-label' => $media->title,
        ])->toString() . '>' . I18n::msg('pool_file_get') . '</button>';
    }

    $ilink = Url::currentBackendPage(array_merge(['file_id' => $media->id, 'rex_file_category' => $rexFileCategory], $argUrl));

    $rows[] = [
        // the search may list media from categories the user has no permission for
        'select' => $hasCategoryPerm && $perm->hasCategoryPerm($media->categoryId)
            ? Html::raw('<input type="checkbox" name="selectedmedia[]" value="' . $media->fileName . '" />')
            : null,
        'thumbnail' => Html::raw('<a href="' . $ilink . '">' . $thumbnail . '</a>'),
        'info' => Html::raw('
                        <h3><a class="rex-u-link-expanded" href="' . $ilink . '">' . escape($media->title) . '</a></h3>
                        ' . $desc . '
                        <p>' . escape($media->fileName) . ' <span class="rex-u-text-muted">' . Formatter::bytes($media->size) . '</span></p>
                    '),
        'updated' => Html::raw('<p class="rex-date">' . Formatter::intlDateTime($media->updateDate) . '</p><p class="rex-u-text-muted">' . escape($media->updateUser) . '</p>'),
        'edit' => Html::raw('<a class="rex-u-link-expanded" href="' . $ilink . '">' . I18n::msg('edit') . '</a>'),
        'functions' => Html::raw(Extension::dispatch(new MediaListFunctions($media, $openerLink))),
    ];
}

if (0 == $pager->getRowCount()) {
    $rows[] = new TableRow([new TableCell(null), new TableCell(I18n::msg('pool_nomediafound'), 5)]);
}

$wordBreak = new HtmlAttributes(['class' => ['rex-u-word-break']]);
$panel .= (string) new Table(
    [
        TableColumn::forKey(Html::raw('<a class="rex-u-link-expanded" href="' . Url::backendController(array_merge(['page' => 'mediapool/upload'], $argUrl)) . '"' . Accesskey::attributes(I18n::msg('pool_file_insert'), 'add') . '><i class="rex-icon rex-icon-add-media"></i></a>'), 'select', ColumnType::Icon),
        TableColumn::forKey(I18n::msg('pool_file_thumbnail'), 'thumbnail', ColumnType::Thumbnail, $wordBreak),
        TableColumn::forKey(I18n::msg('pool_file_info') . ' / ' . I18n::msg('pool_file_description'), 'info', attributes: $wordBreak, title: I18n::msg('pool_file_info')),
        TableColumn::forKey(I18n::msg('pool_last_update'), 'updated'),
        TableColumn::forKey(I18n::msg('pool_file_functions'), 'edit', ColumnType::Action),
        TableColumn::forKey(null, 'functions', ColumnType::Action),
    ],
    $rows,
    footer: Html::raw($footer),
    stickyFooter: true,
    attributes: new HtmlAttributes(['data-hover' => true]),
)->render();

$panel .= '
    </fieldset>
</form>';

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('pool_file_caption', $rexFileCategoryName), false);
$fragment->setVar('options', $toolbar, false);
$fragment->setVar('content', $panel, false);
$content = $fragment->parse('core/page/section.php');

echo $content;
