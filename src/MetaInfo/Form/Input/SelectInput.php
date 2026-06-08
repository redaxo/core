<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Util\Type;

/**
 * @internal
 *
 * @extends AbstractInput<string|array<string>>
 */
final class SelectInput extends AbstractInput
{
    private readonly Select $select;

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
            $this->select->setName(Type::string($value));
        } elseif ('id' == $name) {
            $this->select->setId(Type::string($value));
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
