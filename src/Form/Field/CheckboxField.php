<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Form\AbstractForm;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

use function in_array;
use function Redaxo\Core\View\escape;

class CheckboxField extends AbstractOptionField
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
            $attr .= ' ' . escape($attributeName, 'html_attr') . '="' . escape($attributeValue) . '"';
        }

        $formElements = [];

        foreach ($options as $optName => $optValue) {
            $optId = $id;
            if ('' != $optValue) {
                $optId .= '-' . Str::normalize($optValue, '-');
            }
            $optAttr = $attr . ' id="' . escape($optId) . '"';
            $checked = in_array($optValue, $values) ? ' checked="checked"' : '';

            $n = [];
            $n['label'] = '<label class="control-label" for="' . escape($optId) . '">' . escape($optName) . '</label>';
            $n['field'] = '<input type="checkbox" name="' . escape($name) . '[' . escape($optValue) . ']" value="' . escape($optValue) . '"' . $optAttr . $checked . ' />';
            $formElements[] = $n;
        }

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $fragment->setVar('grouped', true);

        return $fragment->parse('core/form/checkbox.php');
    }
}
