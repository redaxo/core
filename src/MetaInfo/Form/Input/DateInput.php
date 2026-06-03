<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Form\Select\Select;

use function is_array;
use function sprintf;

/**
 * @internal
 *
 * @extends AbstractInput<array{year: numeric-string, month: numeric-string, day: numeric-string, hour?: numeric-string, minute?: numeric-string}>
 */
final class DateInput extends AbstractInput
{
    private ?int $startYear = null;
    private ?int $endYear = null;

    private readonly Select $yearSelect;
    private readonly Select $monthSelect;
    private readonly Select $daySelect;

    public function __construct()
    {
        parent::__construct();

        $this->yearSelect = new Select();
        $this->yearSelect->setAttribute('class', 'rex-form-select-year selectpicker');
        $this->yearSelect->setAttribute('data-width', 'fit');
        $this->yearSelect->setSize(1);

        $range = static function ($start, $end) {
            return array_map(static function ($number) {
                return sprintf('%02d', $number);
            }, range($start, $end));
        };

        $this->monthSelect = new Select();
        $this->monthSelect->addOptions($range(1, 12), true);
        $this->monthSelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->monthSelect->setAttribute('data-width', 'fit');
        $this->monthSelect->setSize(1);

        $this->daySelect = new Select();
        $this->daySelect->addOptions($range(1, 31), true);
        $this->daySelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->daySelect->setAttribute('data-width', 'fit');
        $this->daySelect->setSize(1);
    }

    /**
     * @param int|null $startYear
     * @return void
     */
    public function setStartYear($startYear)
    {
        $this->startYear = $startYear;
    }

    /**
     * @param int|null $endYear
     * @return void
     */
    public function setEndYear($endYear)
    {
        $this->endYear = $endYear;
    }

    public function setValue($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expecting $value to be an array.');
        }

        foreach (['year', 'month', 'day'] as $reqIndex) {
            if (!isset($value[$reqIndex])) {
                throw new InvalidArgumentException('Missing index "' . $reqIndex . '" in $value!');
            }
        }

        $this->yearSelect->setSelected($value['year']);
        $this->monthSelect->setSelected($value['month']);
        $this->daySelect->setSelected($value['day']);

        parent::setValue($value);
    }

    public function setAttribute($name, $value)
    {
        if ('name' == $name) {
            $this->yearSelect->setName($value . '[year]');
            $this->monthSelect->setName($value . '[month]');
            $this->daySelect->setName($value . '[day]');
        } elseif ('id' == $name) {
            $this->yearSelect->setId($value . '_year');
            $this->monthSelect->setId($value . '_month');
            $this->daySelect->setId($value);
        } else {
            $this->yearSelect->setAttribute($name, $value);
            $this->monthSelect->setAttribute($name, $value);
            $this->daySelect->setAttribute($name, $value);
        }

        parent::setAttribute($name, $value);
    }

    /** @return Select */
    public function getDaySelect()
    {
        return $this->daySelect;
    }

    /** @return Select */
    public function getMonthSelect()
    {
        return $this->monthSelect;
    }

    /** @return Select */
    public function getYearSelect()
    {
        return $this->yearSelect;
    }

    public function getHtml()
    {
        $yearSelect = clone $this->yearSelect;
        $yearSelect->addOptions(range($this->startYear ?: 2005, $this->endYear ?: idate('Y') + 10), true);

        return $this->daySelect->get() . $this->monthSelect->get() . $yearSelect->get();
    }
}
