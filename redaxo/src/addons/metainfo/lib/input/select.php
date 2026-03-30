<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 *
 * @extends rex_input<string|array<string>>
 */
class rex_input_select extends rex_input
{
    private rex_select $select;

    public function __construct()
    {
        parent::__construct();

        $this->select = new rex_select();
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

    /** @return rex_select */
    public function getSelect()
    {
        return $this->select;
    }

    public function getHtml()
    {
        return $this->select->get();
    }
}
