<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Form\Select\Select;

use function is_array;
use function sprintf;

/**
 * @internal
 *
 * @extends AbstractInput<array{hour: numeric-string, minute: numeric-string, year?: numeric-string, month?: numeric-string, day?: numeric-string}>
 */
class TimeInput extends AbstractInput
{
    private Select $hourSelect;
    private Select $minuteSelect;

    public function __construct()
    {
        parent::__construct();

        $range = static function ($start, $end) {
            return array_map(static function ($number) {
                return sprintf('%02d', $number);
            }, range($start, $end));
        };

        $this->hourSelect = new Select();
        $this->hourSelect->addOptions($range(0, 23), true);
        $this->hourSelect->setSize(1);
        $this->hourSelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->hourSelect->setAttribute('data-width', 'fit');

        $this->minuteSelect = new Select();
        $this->minuteSelect->addOptions($range(0, 59), true);
        $this->minuteSelect->setSize(1);
        $this->minuteSelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->minuteSelect->setAttribute('data-width', 'fit');
    }

    public function setValue($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expecting $value to be an array.');
        }

        foreach (['hour', 'minute'] as $reqIndex) {
            if (!isset($value[$reqIndex])) {
                throw new InvalidArgumentException('Missing index "' . $reqIndex . '" in $value!');
            }
        }

        $this->hourSelect->setSelected($value['hour']);
        $this->minuteSelect->setSelected($value['minute']);

        parent::setValue($value);
    }

    public function setAttribute($name, $value)
    {
        if ('name' == $name) {
            $this->hourSelect->setName($value . '[hour]');
            $this->minuteSelect->setName($value . '[minute]');
        } elseif ('id' == $name) {
            $this->hourSelect->setId($value . '_hour');
            $this->minuteSelect->setId($value . '_minute');
        } else {
            $this->hourSelect->setAttribute($name, $value);
            $this->minuteSelect->setAttribute($name, $value);
        }
        parent::setAttribute($name, $value);
    }

    /** @return Select */
    public function getHourSelect()
    {
        return $this->hourSelect;
    }

    /** @return Select */
    public function getMinuteSelect()
    {
        return $this->minuteSelect;
    }

    public function getHtml()
    {
        return $this->hourSelect->get() . $this->minuteSelect->get();
    }
}
