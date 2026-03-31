<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\Form\Select\Select;

/**
 * @internal
 *
 * @extends AbstractInput<string|array<string>>
 */
class SelectInput extends AbstractInput
{
    private Select $select;

    public function __construct()
    {
        parent::__construct();

        $this->select = new Select();
        $this->setAttribute('class', 'form-control selectpicker');
    }

    public function setValue($value)
    {
        $this->select->setSelected($value);
        parent::setValue($value);
    }

    public function setAttribute($name, $value)
    {
        if ('name' == $name) {
            $this->select->setName($value);
        } elseif ('id' == $name) {
            $this->select->setId($value);
        } else {
            $this->select->setAttribute($name, $value);
        }

        parent::setAttribute($name, $value);
    }

    /** @return Select */
    public function getSelect()
    {
        return $this->select;
    }

    public function getHtml()
    {
        return $this->select->get();
    }
}
