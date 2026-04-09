<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

final readonly class MediaListVar
{
    /**
     * @param int|string $id
     * @return string
     */
    public static function getWidget($id, $name, $value, array $args = [])
    {
        $openParams = '';
        if (isset($args['category']) && ($category = (int) $args['category'])) {
            $openParams .= '&amp;rex_file_category=' . $category;
        }

        foreach ($args as $aname => $avalue) {
            $openParams .= '&amp;args[' . $aname . ']=' . urlencode($avalue);
        }

        $wdgtClass = ' rex-js-widget-medialist';
        if (isset($args['preview']) && $args['preview']) {
            $wdgtClass .= ' rex-js-widget-preview rex-js-widget-preview-media-manager';
        }

        $options = '';
        $medialistarray = null === $value ? [] : explode(',', $value);
        foreach ($medialistarray as $file) {
            if ('' != $file) {
                $options .= '<option value="' . $file . '">' . $file . '</option>';
            }
        }

        $disabled = ' disabled';
        $openFunc = '';
        $addFunc = '';
        $deleteFunc = '';
        $viewFunc = '';
        $quotedId = "'" . escape($id, 'js') . "'";
        if (Core::requireUser()->getComplexPerm('media')->hasMediaPerm()) {
            $disabled = '';
            $openFunc = 'openREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
            $addFunc = 'addREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXMedialist(' . $quotedId . ');';
            $viewFunc = 'viewREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
        }

        $e = [];
        $e['before'] = '<div class="rex-js-widget' . $wdgtClass . '">';
        $e['field'] = '<select class="form-control" name="REX_MEDIALIST_SELECT[' . $id . ']" id="REX_MEDIALIST_SELECT_' . $id . '" size="10">' . $options . '</select><input type="hidden" name="' . $name . '" id="REX_MEDIALIST_' . $id . '" value="' . $value . '" />';
        $e['moveButtons'] = '
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'top\');return false;" title="' . I18n::msg('var_medialist_move_top') . '"><i class="rex-icon rex-icon-top"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'up\');return false;" title="' . I18n::msg('var_medialist_move_up') . '"><i class="rex-icon rex-icon-up"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'down\');return false;" title="' . I18n::msg('var_medialist_move_down') . '"><i class="rex-icon rex-icon-down"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'bottom\');return false;" title="' . I18n::msg('var_medialist_move_bottom') . '"><i class="rex-icon rex-icon-bottom"></i></a>';
        $e['functionButtons'] = '
                <a href="#" class="btn btn-popup" onclick="' . $openFunc . 'return false;" title="' . I18n::msg('var_media_open') . '"' . $disabled . '><i class="rex-icon rex-icon-open-mediapool"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $addFunc . 'return false;" title="' . I18n::msg('var_media_new') . '"' . $disabled . '><i class="rex-icon rex-icon-add-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $deleteFunc . 'return false;" title="' . I18n::msg('var_media_remove') . '"' . $disabled . '><i class="rex-icon rex-icon-delete-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $viewFunc . 'return false;" title="' . I18n::msg('var_media_view') . '"' . $disabled . '><i class="rex-icon rex-icon-view-media"></i></a>';
        $e['after'] = '<div class="rex-js-media-preview"></div></div>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget_list.php');
    }
}
