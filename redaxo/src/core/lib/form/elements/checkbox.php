<?php

/**
 * @package redaxo\core\form
 */
class rex_form_checkbox_element extends rex_form_options_element
{
    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?rex_form_base $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);
        // Jede checkbox bekommt eingenes Label
        $this->setLabel('');
    }

    protected function formatLabel()
    {
        // Da Jedes Feld schon ein Label hat, hier nur eine "Ueberschrift" anbringen
        $label = $this->getLabel();

        if ('' != $label) {
            $label = '<label class="control-label">' . $label . '</label>';
        }

        return $label;
    }

    /** @return string */
    public function formatElement()
    {
        $values = explode('|', trim($this->getValue() ?? '', '|'));
        $options = $this->getOptions();
        $name = $this->getAttribute('name');
        $id = $this->getAttribute('id');

        $attr = '';
        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            if ('name' == $attributeName || 'id' == $attributeName) {
                continue;
            }
            $attr .= ' ' . rex_escape($attributeName, 'html_attr') . '="' . rex_escape($attributeValue) . '"';
        }

        $formElements = [];

        foreach ($options as $optName => $optValue) {
            $optId = $id;
            if ('' != $optValue) {
                $optId .= '-' . rex_string::normalize($optValue, '-');
            }
            $optAttr = $attr . ' id="' . rex_escape($optId) . '"';
            $checked = in_array($optValue, $values) ? ' checked="checked"' : '';

            $n = [];
            $n['label'] = '<label class="control-label" for="' . rex_escape($optId) . '">' . rex_escape($optName) . '</label>';
            $n['field'] = '<input type="checkbox" name="' . rex_escape($name) . '[' . rex_escape($optValue) . ']" value="' . rex_escape($optValue) . '"' . $optAttr . $checked . ' />';
            $formElements[] = $n;
        }

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $fragment->setVar('grouped', true);

        return $fragment->parse('core/form/checkbox.php');
    }
}
