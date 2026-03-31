<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Translation\I18n;

use function call_user_func;

class PriorityField extends SelectField
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
     * @var Form
     * @psalm-suppress NonInvariantDocblockPropertyType
     */
    protected $table;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag, Form $form, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);
        $this->table = $form;
        $this->select->setSize(1);

        Extension::register('REX_FORM_SAVED', function (ExtensionPoint $ep) {
            $this->organizePriorities($ep);
        });
        Extension::register('REX_FORM_DELETED', function (ExtensionPoint $ep) {
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
        $sql = Sql::factory();
        $sql->setQuery($qry, $params);

        $this->select->addOption(I18n::msg($this->firstOptionMsg), 1);
        $value = 1;
        foreach ($sql as $opt) {
            $value = $opt->getValue($name) + 1;
            $label = $opt->getValue($this->labelField);

            if ($this->labelCallback) {
                $label = call_user_func($this->labelCallback, $label);
            }

            $this->select->addOption(I18n::rawMsg($this->optionMsg, $label), $value);
        }
        if (!$this->table->isEditMode()) {
            $this->select->setSelected($value);
        }

        return parent::formatElement();
    }

    /** @return void */
    public function organizePriorities(ExtensionPoint $ep)
    {
        if ($this->table->equals($ep->getParam('form'))) {
            $name = $this->getFieldName();

            Util::organizePriorities(
                $this->table->getTableName(),
                $name,
                $this->whereCondition,
                $name . ', updatedate desc',
            );
        }
    }
}
