<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Form\AbstractForm;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

class RadioField extends AbstractOptionField
{
    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?AbstractForm $form = null, array $attributes = [])
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
            $attr .= ' ' . escape($attributeName, 'html_attr') . '="' . escape($attributeValue) . '"';
        }

        $formElements = [];

        foreach ($options as $optName => $optValue) {
            $checked = $optValue == $value ? ' checked="checked"' : '';
            $optId = $id . '-' . Str::normalize($optValue, '-');
            $optAttr = $attr . ' id="' . $optId . '"';

            $n = [];
            $n['label'] = '<label class="control-label" for="' . $optId . '">' . escape($optName) . '</label>';
            $n['field'] = '<input type="radio" value="' . escape($optValue) . '"' . $optAttr . $checked . ' />';
            $formElements[] = $n;
        }

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);

        return $fragment->parse('core/form/radio.php');
    }
}
