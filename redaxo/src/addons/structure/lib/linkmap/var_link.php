<?php

/**
 * REX_LINK.
 *
 * Attribute:
 *   - category  => Kategorie in die beim oeffnen der Linkmapw gesprungen werden soll
 *
 * @package redaxo\structure
 */
class rex_var_link extends rex_var
{
    protected function getOutput()
    {
        $id = $this->getArg('id', 0, true);
        if (!in_array($this->getContext(), ['module', 'action']) || !is_numeric($id) || $id < 1 || $id > 10) {
            return false;
        }

        $value = $this->getContextData()->getValue('link' . $id);

        if ($this->hasArg('isset') && $this->getArg('isset')) {
            return $value ? 'true' : 'false';
        }

        if ($this->hasArg('widget') && $this->getArg('widget')) {
            if (!$this->environmentIs(self::ENV_INPUT)) {
                return false;
            }
            $args = [];
            foreach (['category'] as $key) {
                if ($this->hasArg($key)) {
                    $args[$key] = $this->getArg($key);
                }
            }
            $value = self::getWidget($id, 'REX_INPUT_LINK[' . $id . ']', $value, $args);
        } else {
            if ($value && $this->hasArg('output') && 'id' != $this->getArg('output')) {
                [$articleId, $hash] = array_pad(explode('#', $value, 2), 2, null);
                return 'rex_getUrl(' . self::quote($articleId) . ')' . ($hash ? ' . \'#\' . ' . self::quote($hash) : '') . ';';
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
        $artName = '';
        [$articleId, $hash] = array_pad(explode('#', $value, 2), 2, null);
        $art = rex_article::get((int) $articleId);
        $category = rex_category::getCurrent() ? rex_category::getCurrent()->getId() : 0; // Aktuelle Kategorie vorauswählen

        // Falls ein Artikel vorausgewählt ist, dessen Namen anzeigen und beim Öffnen der Linkmap dessen Kategorie anzeigen
        if ($art instanceof rex_article) {
            $artName = trim(sprintf('%s [%s]', $art->getName(), $art->getId()));
            if (null !== $hash && '' !== $hash) {
                $artName .= ' #' . $hash;
            }
            $category = $art->getCategoryId();
        }

        // Falls ein Kategorie-Parameter angegeben wurde, die Linkmap in dieser Kategorie öffnen
        if (isset($args['category'])) {
            $category = (int) $args['category'];
        }

        $openParams = '&clang=' . rex_clang::getCurrentId() . '&category_id=' . $category;

        $class = ' rex-disabled';
        $openFunc = '';
        $deleteFunc = '';
        if (rex::requireUser()->getComplexPerm('structure')->hasStructurePerm()) {
            $class = '';
            $escapedId = rex_escape($id, 'js') ?? '';
            $openFunc = 'openLinkMap(\'REX_LINK_' . $escapedId . '\', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXLink(\'' . $escapedId . '\');';
        }

        $id = rex_escape((string) $id, 'html_attr') ?? '';
        $e = [];
        $e['field'] = '<input class="form-control" type="text" name="REX_LINK_NAME[' . $id . ']" value="' . rex_escape($artName) . '" id="REX_LINK_' . $id . '_NAME" readonly="readonly" /><input type="hidden" name="' . $name . '" id="REX_LINK_' . $id . '" value="' . $value . '" />';
        $e['functionButtons'] = '
                        <a href="#" class="btn btn-popup' . $class . '" onclick="' . $openFunc . 'return false;" title="' . rex_i18n::msg('var_link_open') . '"><i class="rex-icon rex-icon-open-linkmap"></i></a>
                        <a href="#" class="btn btn-popup' . $class . '" onclick="' . $deleteFunc . 'return false;" title="' . rex_i18n::msg('var_link_delete') . '"><i class="rex-icon rex-icon-delete-link"></i></a>';

        $fragment = new rex_fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget.php');
    }
}
