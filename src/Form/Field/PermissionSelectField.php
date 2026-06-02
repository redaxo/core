<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Security\ComplexPermission;

/**
 * @internal
 */
final class PermissionSelectField extends SelectField
{
    private string $checkboxLabel = 'All';

    public function getSaveValue()
    {
        $value = $this->getValue();

        if ($value && str_contains($value, '|' . ComplexPermission::ALL . '|')) {
            return ComplexPermission::ALL;
        }

        return $value;
    }

    /**
     * @param string $label
     * @return void
     */
    public function setCheckboxLabel($label)
    {
        $this->checkboxLabel = $label;
    }

    /** @return string */
    public function get()
    {
        $field = new CheckboxField('', $this->table);
        $field->setAttribute('name', $this->getAttribute('name', ''));
        $field->setAttribute('id', $this->getAttribute('id', ''));
        if (ComplexPermission::ALL == trim((string) $this->getValue(), '|')) {
            $field->setValue('|' . ComplexPermission::ALL . '|');
        }
        $field->addOption($this->checkboxLabel, ComplexPermission::ALL);
        $this->setAttribute('class', 'form-control');
        return $field->get() . parent::get();
    }
}
