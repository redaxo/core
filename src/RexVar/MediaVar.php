<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

final readonly class MediaVar
{
    /**
     * @param int|null $category Open the mediapool in this category
     * @param list<string> $types Restrict the mediapool (listing and upload) to these file extensions
     * @param bool $preview Show a preview of the selected file
     */
    public static function getWidget(int|string $id, string $name, ?string $value, ?int $category = null, array $types = [], bool $preview = false): string
    {
        $openParams = '';
        if ($category) {
            $openParams .= '&amp;rex_file_category=' . $category;
        }
        if ($types) {
            $openParams .= '&amp;types=' . urlencode(implode(',', $types));
        }

        $wdgtClass = ' rex-js-widget-media';
        if ($preview) {
            $wdgtClass .= ' rex-js-widget-preview rex-js-widget-preview-media-manager';
        }

        $disabled = ' disabled';
        $openFunc = '';
        $addFunc = '';
        $deleteFunc = '';
        $viewFunc = '';
        if (Core::requireUser()->getComplexPerm('media')->hasMediaPerm()) {
            $disabled = '';
            $quotedId = "'" . escape($id, 'js') . "'";
            $openFunc = 'openREXMedia(' . $quotedId . ', \'' . $openParams . '\');';
            $addFunc = 'addREXMedia(' . $quotedId . ', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXMedia(' . $quotedId . ');';
            $viewFunc = 'viewREXMedia(' . $quotedId . ', \'' . $openParams . '\');';
        }

        $e = [];
        $e['before'] = '<div class="rex-js-widget' . $wdgtClass . '">';
        $e['field'] = '<input class="form-control" type="text" name="' . $name . '" value="' . ($value ?? '') . '" id="REX_MEDIA_' . $id . '" readonly />';
        $e['functionButtons'] = '
                <a href="#" class="btn btn-popup" onclick="' . $openFunc . 'return false;" title="' . I18n::msg('var_media_open') . '"' . $disabled . '><i class="rex-icon rex-icon-open-mediapool"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $addFunc . 'return false;" title="' . I18n::msg('var_media_new') . '"' . $disabled . '><i class="rex-icon rex-icon-add-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $deleteFunc . 'return false;" title="' . I18n::msg('var_media_remove') . '"' . $disabled . '><i class="rex-icon rex-icon-delete-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $viewFunc . 'return false;" title="' . I18n::msg('var_media_view') . '"' . $disabled . '><i class="rex-icon rex-icon-view-media"></i></a>';
        $e['after'] = '<div class="rex-js-media-preview"></div></div>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget.php');
    }
}
