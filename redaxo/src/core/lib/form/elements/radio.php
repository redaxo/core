<?php

/**
 * @package redaxo\core\form
 */
class rex_form_radio_element extends rex_form_options_element
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
        // Jedes radio bekommt eingenes Label
    }

    /** @return string */
    protected function formatLabel()
    {
        // Da Jedes Feld schon ein Label hat, hier nur eine "Ueberschrift" anbringen
        return '<label class="control-label">' . $this->getLabel() . '</label>';
    }

    /** @return string */
    public function formatElement()
    {
        $value = $this->getValue();
        $options = $this->getOptions();
        $id = $this->getAttribute('id');

        $attr = '';
        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            if ('id' == $attributeName) {
                continue;
            }
            $attr .= ' ' . rex_escape($attributeName, 'html_attr') . '="' . rex_escape($attributeValue) . '"';
        }

        $formElements = [];

        foreach ($options as $optName => $optValue) {
            $checked = $optValue == $value ? ' checked="checked"' : '';
            $optId = $id . '-' . rex_string::normalize($optValue, '-');
            $optAttr = $attr . ' id="' . $optId . '"';

            $n = [];
            $n['label'] = '<label class="control-label" for="' . $optId . '">' . rex_escape($optName) . '</label>';
            $n['field'] = '<input type="radio" value="' . rex_escape($optValue) . '"' . $optAttr . $checked . ' />';
            $formElements[] = $n;
        }

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);

        return $fragment->parse('core/form/radio.php');
    }
}
