<?php

/**
 * @package redaxo\core\form
 */
class rex_form_prio_element extends rex_form_select_element
{
    /** @var string */
    private $labelField = '';
    /** @var callable(string):string */
    private $labelCallback;
    /** @var string */
    private $whereCondition = '';
    /** @var string */
    private $firstOptionMsg = 'form_field_first_priority';
    /** @var string */
    private $optionMsg = 'form_field_after_priority';
    /**
     * @var rex_form
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $table;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag, rex_form $form, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);
        $this->table = $form;
        $this->select->setSize(1);

        rex_extension::register('REX_FORM_SAVED', function (rex_extension_point $ep) {
            $this->organizePriorities($ep);
        });
        rex_extension::register('REX_FORM_DELETED', function (rex_extension_point $ep) {
            $this->organizePriorities($ep);
        });
    }

    /**
     * Setzt die Datenbankspalte, die das Label für die zu priorisierenden Elemente darstellt.
     *
     * @param string $labelField
     * @return void
     */
    public function setLabelField($labelField)
    {
        $this->labelField = $labelField;
    }

    /** @return void */
    public function setLabelCallback(callable $labelCallback)
    {
        $this->labelCallback = $labelCallback;
    }

    /** @return void */
    public function setWhereCondition($whereCondition)
    {
        $this->whereCondition = $whereCondition;
    }

    /**
     * @deprecated this method has no effect
     * @return void
     */
    public function setPrimaryKey()
    {
        // nothing todo.. left here for BC reasons
    }

    public function formatElement()
    {
        $name = $this->getFieldName();

        $qry = 'SELECT ' . $this->labelField . ',' . $name . ' FROM ' . $this->table->getTableName() . ' WHERE 1=1';
        if ('' != $this->whereCondition) {
            $qry .= ' AND (' . $this->whereCondition . ')';
        }

        $params = [];

        // Im Edit Mode das Feld selbst nicht als Position einfügen
        if ($this->table->isEditMode()) {
            $qry .= ' AND (' . $name . ' != ?)';
            $params[] = $this->getValue();
        }

        $qry .= ' ORDER BY ' . $name;
        $sql = rex_sql::factory();
        $sql->setQuery($qry, $params);

        $this->select->addOption(rex_i18n::msg($this->firstOptionMsg), 1);
        $value = 1;
        foreach ($sql as $opt) {
            $value = $opt->getValue($name) + 1;
            $label = $opt->getValue($this->labelField);

            if ($this->labelCallback) {
                $label = call_user_func($this->labelCallback, $label);
            }

            $this->select->addOption(rex_i18n::rawMsg($this->optionMsg, $label), $value);
        }
        if (!$this->table->isEditMode()) {
            $this->select->setSelected($value);
        }

        return parent::formatElement();
    }

    /** @return void */
    public function organizePriorities(rex_extension_point $ep)
    {
        if ($this->table->equals($ep->getParam('form'))) {
            $name = $this->getFieldName();

            rex_sql_util::organizePriorities(
                $this->table->getTableName(),
                $name,
                $this->whereCondition,
                $name . ', updatedate desc',
            );
        }
    }
}
