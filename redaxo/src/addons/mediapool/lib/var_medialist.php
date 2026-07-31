<?php

/**
 * REX_MEDIALIST[1].
 *
 * Attribute:
 *   - category  => Kategorie in die beim oeffnen des Medienpools gesprungen werden soll
 *   - types     => Filter für Dateiendungen die im Medienpool zur Auswahl stehen sollen
 *   - preview   => Bei Bildertypen ein Vorschaubild einblenden
 *
 * @package redaxo\mediapool
 */
class rex_var_medialist extends rex_var
{
    protected function getOutput()
    {
        $id = $this->getArg('id', 0, true);
        if (!in_array($this->getContext(), ['module', 'action']) || !is_numeric($id) || $id < 1 || $id > 10) {
            return false;
        }

        $value = $this->getContextData()->getValue('medialist' . $id);

        if ($this->hasArg('isset') && $this->getArg('isset')) {
            return $value ? 'true' : 'false';
        }

        if ($this->hasArg('widget') && $this->getArg('widget')) {
            if (!$this->environmentIs(self::ENV_INPUT)) {
                return false;
            }
            $args = [];
            foreach (['category', 'preview', 'types'] as $key) {
                if ($this->hasArg($key)) {
                    $args[$key] = $this->getArg($key);
                }
            }
            $value = self::getWidget($id, 'REX_INPUT_MEDIALIST[' . $id . ']', $value, $args);
        }

        return self::quote($value);
    }

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
            $wdgtClass .= ' rex-js-widget-preview';
            if (rex_addon::get('media_manager')->isAvailable()) {
                $wdgtClass .= ' rex-js-widget-preview-media-manager';
            }
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
        $quotedId = "'" . rex_escape($id, 'js') . "'";
        if (rex::requireUser()->getComplexPerm('media')->hasMediaPerm()) {
            $disabled = '';
            $openFunc = 'openREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
            $addFunc = 'addREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXMedialist(' . $quotedId . ');';
            $viewFunc = 'viewREXMedialist(' . $quotedId . ', \'' . $openParams . '\');';
        }

        $e = [];
        $e['before'] = '<div class="rex-js-widget' . $wdgtClass . '">';

        $e['before'] = '<div class="rex-js-widget' . $wdgtClass . '"
            data-id="' . rex_escape($id) . '"
            data-category="' . (int) rex_escape($args['category'] ?? 0) . '"
            data-params="' . json_encode($args) . '"
            >';


        $e['field'] = '<select class="form-control" name="REX_MEDIALIST_SELECT[' . $id . ']" id="REX_MEDIALIST_SELECT_' . $id . '" size="10">' . $options . '</select><input type="hidden" name="' . $name . '" id="REX_MEDIALIST_' . $id . '" value="' . $value . '" />';
        $e['moveButtons'] = '
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'top\');return false;" title="' . rex_i18n::msg('var_medialist_move_top') . '"><i class="rex-icon rex-icon-top"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'up\');return false;" title="' . rex_i18n::msg('var_medialist_move_up') . '"><i class="rex-icon rex-icon-up"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'down\');return false;" title="' . rex_i18n::msg('var_medialist_move_down') . '"><i class="rex-icon rex-icon-down"></i></a>
                <a href="#" class="btn btn-popup" onclick="moveREXMedialist(' . $quotedId . ',\'bottom\');return false;" title="' . rex_i18n::msg('var_medialist_move_bottom') . '"><i class="rex-icon rex-icon-bottom"></i></a>';
        $e['functionButtons'] = '
                <a href="#" class="btn btn-popup" onclick="' . $openFunc . 'return false;" title="' . rex_i18n::msg('var_media_open') . '"' . $disabled . '><i class="rex-icon rex-icon-open-mediapool"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $addFunc . 'return false;" title="' . rex_i18n::msg('var_media_new') . '"' . $disabled . '><i class="rex-icon rex-icon-add-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $deleteFunc . 'return false;" title="' . rex_i18n::msg('var_media_remove') . '"' . $disabled . '><i class="rex-icon rex-icon-delete-media"></i></a>
                <a href="#" class="btn btn-popup" onclick="' . $viewFunc . 'return false;" title="' . rex_i18n::msg('var_media_view') . '"' . $disabled . '><i class="rex-icon rex-icon-view-media"></i></a>';
        $e['after'] = '<div class="rex-js-media-preview"></div></div>';

        $fragment = new rex_fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget_list.php');
    }
}
