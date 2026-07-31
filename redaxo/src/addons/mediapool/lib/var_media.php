<?php

/**
 * REX_MEDIA[1].
 *
 * Attribute:
 *   - category  => Kategorie in die beim oeffnen des Medienpools gesprungen werden soll
 *   - types     => Filter für Dateiendungen die im Medienpool zur Auswahl stehen sollen
 *   - preview   => Bei Bildertypen ein Vorschaubild einblenden
 *   - output    => "mimetype": Mimetype des Bildes ausgeben
 *
 * @package redaxo\mediapool
 */
class rex_var_media extends rex_var
{
    protected function getOutput()
    {
        $id = $this->getArg('id', 0, true);
        if (!in_array($this->getContext(), ['module', 'action']) || !is_numeric($id) || $id < 1 || $id > 10) {
            return false;
        }

        $value = $this->getContextData()->getValue('media' . $id);

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
            $value = self::getWidget($id, 'REX_INPUT_MEDIA[' . $id . ']', $value, $args);
        } else {
            if ($this->hasArg('output') && 'mimetype' == $this->getArg('output')) {
                $media = rex_media::get($value);
                if ($media) {
                    $value = $media->getType();
                }
            } elseif ($this->hasArg('field') && $field = $this->getParsedArg('field')) {
                return 'htmlspecialchars(rex_media::get(' . self::quote($value) . ')->getValue(' . $field . '))';
            }
        }

        return self::quote($value);
    }

    /**
     * @param int|string $id
     * @return string
     */
    public static function getWidget($id, $name, $value, array $args = [])
    {
        // TODO
        $openParams = '';
        if (isset($args['category']) && ($category = (int) $args['category'])) {
            $openParams .= '&amp;rex_file_category=' . $category;
        }

        foreach ($args as $aname => $avalue) {
            $openParams .= '&amp;args[' . urlencode($aname) . ']=' . urlencode($avalue);
        }

        $wdgtClass = ' rex-js-widget-media';
        if (isset($args['preview']) && $args['preview']) {
            $wdgtClass .= ' rex-js-widget-preview';
            if (rex_addon::get('media_manager')->isAvailable()) {
                $wdgtClass .= ' rex-js-widget-preview-media-manager';
            }
        }

        $disabled = ' disabled';
        if (rex::requireUser()->getComplexPerm('media')->hasMediaPerm()) {
            $disabled = '';
        }

        $e = [];
        $e['before'] = '<div class="rex-js-widget' . $wdgtClass . '"
            data-id="' . rex_escape($id) . '"
            data-category="' . (int) rex_escape($args['category'] ?? 0) . '"
            data-params="' . json_encode($args) . '"
            >';
        $e['field'] = '<input class="form-control" type="text" name="' . $name . '" value="' . $value . '" id="REX_MEDIA_' . $id . '" readonly />';
        $e['functionButtons'] = '
                <a class="btn btn-popup rex-js-widget-media-open" title="' . rex_i18n::msg('var_media_open') . '"' . $disabled . '><i class="rex-icon rex-icon-open-mediapool"></i></a>
                <a class="btn btn-popup rex-js-widget-media-add" title="' . rex_i18n::msg('var_media_new') . '"' . $disabled . '><i class="rex-icon rex-icon-add-media"></i></a>
                <a class="btn btn-popup rex-js-widget-media-delete" title="' . rex_i18n::msg('var_media_remove') . '"' . $disabled . '><i class="rex-icon rex-icon-delete-media"></i></a>
                <a class="btn btn-popup rex-js-widget-media-view" title="' . rex_i18n::msg('var_media_view') . '"' . $disabled . '><i class="rex-icon rex-icon-view-media"></i></a>';
        $e['after'] = '<div class="rex-js-media-preview"></div></div>';

        $fragment = new rex_fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget.php');
    }
}
