<?php

/**
 * @package redaxo\core\form
 */
class rex_form_select_element extends rex_form_element
{
    /** @var rex_select */
    protected $select;
    /** @var non-empty-string */
    private $separator = '|';

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?rex_form_base $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);

        $this->select = new rex_select();
    }

    public function formatElement()
    {
        $multipleSelect = false;

        // Hier die Attribute des Elements an den Select weitergeben, damit diese angezeigt werden
        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            $this->select->setAttribute($attributeName, $attributeValue);
        }

        if ($this->select->hasAttribute('multiple')) {
            $multipleSelect = true;
        }

        if ($multipleSelect) {
            $this->setAttribute('name', $this->getAttribute('name') . '[]');

            $selectedOptions = explode($this->separator, trim($this->getValue() ?? '', $this->separator));
            if ('' != $selectedOptions[0]) {
                foreach ($selectedOptions as $selectedOption) {
                    $this->select->setSelected($selectedOption);
                }
            }
        } else {
            $this->select->setSelected($this->getValue());
        }

        $this->select->setName($this->getAttribute('name'));
        return $this->select->get();
    }

    /** @return void */
    public function setSeparator($separator)
    {
        $this->separator = $separator;
    }

    /** @return rex_select */
    public function getSelect()
    {
        return $this->select;
    }

    /** @return void */
    public function setSelect(rex_select $select)
    {
        $this->select = $select;
        if ($select->hasAttribute('multiple')) {
            $this->setAttribute('multiple', $select->getAttribute('multiple'));
        }
    }
}
