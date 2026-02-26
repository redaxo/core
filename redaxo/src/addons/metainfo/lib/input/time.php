<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 *
 * @extends rex_input<array{hour: numeric-string, minute: numeric-string, year?: numeric-string, month?: numeric-string, day?: numeric-string}>
 */
class rex_input_time extends rex_input
{
    private rex_select $hourSelect;
    private rex_select $minuteSelect;

    public function __construct()
    {
        parent::__construct();

        $range = static function ($start, $end) {
            return array_map(static function ($number) {
                return sprintf('%02d', $number);
            }, range($start, $end));
        };

        $this->hourSelect = new rex_select();
        $this->hourSelect->addOptions($range(0, 23), true);
        $this->hourSelect->setSize(1);
        $this->hourSelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->hourSelect->setAttribute('data-width', 'fit');

        $this->minuteSelect = new rex_select();
        $this->minuteSelect->addOptions($range(0, 59), true);
        $this->minuteSelect->setSize(1);
        $this->minuteSelect->setAttribute('class', 'rex-form-select-date selectpicker');
        $this->minuteSelect->setAttribute('data-width', 'fit');
    }

    public function setValue($value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Expecting $value to be an array!');
        }

        foreach (['hour', 'minute'] as $reqIndex) {
            if (!isset($value[$reqIndex])) {
                throw new rex_exception('Missing index "' . $reqIndex . '" in $value!');
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

    /** @return rex_select */
    public function getHourSelect()
    {
        return $this->hourSelect;
    }

    /** @return rex_select */
    public function getMinuteSelect()
    {
        return $this->minuteSelect;
    }

    public function getHtml()
    {
        return $this->hourSelect->get() . $this->minuteSelect->get();
    }
}
