<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Core;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * REX_MEDIA[1].
 *
 * Attribute:
 *   - category  => Kategorie in die beim oeffnen des Medienpools gesprungen werden soll
 *   - types     => Filter für Dateiendungen die im Medienpool zur Auswahl stehen sollen
 *   - preview   => Bei Bildertypen ein Vorschaubild einblenden
 *   - output    => "mimetype": Mimetype des Bildes ausgeben
 */
class MediaVar extends RexVar
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
                $media = Media::get($value);
                if ($media) {
                    $value = $media->getType();
                }
            } elseif ($this->hasArg('field') && $field = $this->getParsedArg('field')) {
                return 'htmlspecialchars(\\' . Media::class . '::get(' . self::quote($value) . ')->getValue(' . $field . '))';
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
        $openParams = '';
        if (isset($args['category']) && ($category = (int) $args['category'])) {
            $openParams .= '&amp;rex_file_category=' . $category;
        }

        foreach ($args as $aname => $avalue) {
            $openParams .= '&amp;args[' . urlencode($aname) . ']=' . urlencode($avalue);
        }

        $wdgtClass = ' rex-js-widget-media';
        if (isset($args['preview']) && $args['preview']) {
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
        $e['field'] = '<input class="form-control" type="text" name="' . $name . '" value="' . $value . '" id="REX_MEDIA_' . $id . '" readonly />';
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
