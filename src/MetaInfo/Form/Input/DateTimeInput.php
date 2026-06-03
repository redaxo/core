<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Form\Select\Select;

use function is_array;

/**
 * @internal
 *
 * @extends AbstractInput<array{year: numeric-string, month: numeric-string, day: numeric-string, hour: numeric-string, minute: numeric-string}>
 */
final class DateTimeInput extends AbstractInput
{
    private readonly DateInput $dateInput;
    private readonly TimeInput $timeInput;

    public function __construct()
    {
        parent::__construct();

        $this->dateInput = new DateInput();
        $this->timeInput = new TimeInput();
    }

    /**
     * @param int|null $startYear
     * @return void
     */
    public function setStartYear($startYear)
    {
        $this->dateInput->setStartYear($startYear);
    }

    /**
     * @param int|null $endYear
     * @return void
     */
    public function setEndYear($endYear)
    {
        $this->dateInput->setEndYear($endYear);
    }

    public function setValue($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expecting $value to be an array.');
        }

        $this->dateInput->setValue($value);
        $this->timeInput->setValue($value);

        parent::setValue($value);
    }

    public function getValue()
    {
        return array_merge($this->dateInput->getValue(), $this->timeInput->getValue());
    }

    public function setAttribute($name, $value)
    {
        $this->dateInput->setAttribute($name, $value);
        $this->timeInput->setAttribute($name, $value);

        parent::setAttribute($name, $value);
    }

    /** @return Select */
    public function getDaySelect()
    {
        return $this->dateInput->getDaySelect();
    }

    /** @return Select */
    public function getMonthSelect()
    {
        return $this->dateInput->getMonthSelect();
    }

    /** @return Select */
    public function getYearSelect()
    {
        return $this->dateInput->getYearSelect();
    }

    /** @return Select */
    public function getHourSelect()
    {
        return $this->timeInput->getHourSelect();
    }

    /** @return Select */
    public function getMinuteSelect()
    {
        return $this->timeInput->getMinuteSelect();
    }

    public function getHtml()
    {
        return '<span class="rex-form-group-nowrap">' . $this->dateInput->getHtml() . '</span> <span class="rex-form-select-separator">-</span> <span class="rex-form-group-nowrap">' . $this->timeInput->getHTML() . '</span>';
    }
}
