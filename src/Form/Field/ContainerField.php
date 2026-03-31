<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Form\AbstractForm;
use Redaxo\Core\Util\Type;

use function in_array;
use function is_string;
use function Redaxo\Core\View\escape;

class ContainerField extends BaseField
{
    /** @var array<string, list<BaseField>> */
    private $fields = [];
    /** @var bool */
    private $multiple = true;
    /** @var string */
    private $active;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?AbstractForm $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @param bool $multiple
     * @return void
     */
    public function setMultiple($multiple = true)
    {
        $this->multiple = $multiple;
    }

    /**
     * @param string $group
     * @return void
     */
    public function setActive($group)
    {
        $this->active = $group;
    }

    /**
     * @param string $type
     * @param string $name
     *
     * @return BaseField
     */
    public function addField($type, $name, $value = null, array $attributes = [])
    {
        return $this->addGroupedField('elementContainer', $type, $name, $value, $attributes);
    }

    /**
     * @param string $group
     * @param string $type
     * @param string $name
     *
     * @return BaseField
     */
    public function addGroupedField($group, $type, $name, $value = null, array $attributes = [])
    {
        $field = $this->table->createInput($type, $name, $value, $attributes);

        if (!isset($this->fields[$group])) {
            $this->fields[$group] = [];
        }

        $field->setAttribute('id', $this->getAttribute('id') . '-' . $group . '-' . $field->getFieldName());
        $field->setAttribute('name', $this->getAttribute('name') . '[' . $group . '][' . $field->getFieldName() . ']');
        $field->setValue($value);

        $this->fields[$group][] = $field;
        return $field;
    }

    /** @return array<string, list<BaseField>> */
    public function getFields()
    {
        return $this->fields;
    }

    /** @return void */
    protected function prepareInnerFields()
    {
        $values = $this->getValue();
        if (null === $values) {
            return;
        }
        if (is_string($values)) {
            $values = json_decode($values, true);
            if (!$this->multiple) {
                $values = [$this->active => $values];
            }
        }
        $values = Type::array($values);

        foreach ($this->fields as $group => $groupFields) {
            if (!$this->multiple && $this->active && $this->active !== $group) {
                continue;
            }

            foreach ($groupFields as $field) {
                if (isset($values[$group][$field->getFieldName()])) {
                    $field->setValue($values[$group][$field->getFieldName()]);
                }
            }
        }
    }

    /** @return string */
    public function formatElement()
    {
        $this->prepareInnerFields();

        $attr = '';
        // Folgende attribute filtern:
        // - name: der container selbst ist kein feld, daher hat er keinen namen
        // - id:   eine id vergeben wir automatisiert pro gruppe
        $attributeFilter = ['id', 'name'];
        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            if (in_array($attributeName, $attributeFilter)) {
                continue;
            }

            $attr .= ' ' . escape($attributeName, 'html_attr') . '="' . escape($attributeValue) . '"';
        }

        $format = '';
        foreach ($this->fields as $group => $groupFields) {
            $format .= '<div id="rex-' . escape($group) . '"' . $attr . '>';
            foreach ($groupFields as $field) {
                $format .= $field->get();
            }
            $format .= '</div>';
        }
        return $format;
    }

    /** @return string */
    protected function getFragment()
    {
        return 'core/form/container.php';
    }

    /** @return string */
    public function getSaveValue()
    {
        $this->prepareInnerFields();

        $value = [];
        if ($this->multiple) {
            foreach ($this->fields as $group => $groupFields) {
                foreach ($groupFields as $field) {
                    // read-only-fields nicht speichern
                    if (!$field->isReadOnly()) {
                        $value[$group][$field->getFieldName()] = $field->getSaveValue();
                    }
                }
            }
        } elseif ($this->active && isset($this->fields[$this->active])) {
            foreach ($this->fields[$this->active] as $field) {
                // read-only-fields nicht speichern
                if (!$field->isReadOnly()) {
                    $value[$field->getFieldName()] = $field->getSaveValue();
                }
            }
        }
        return json_encode($value);
    }
}
