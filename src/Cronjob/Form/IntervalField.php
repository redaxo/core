<?php

namespace Redaxo\Core\Cronjob\Form;

use Generator;
use Redaxo\Core\Form\Field\BaseField;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Fragment;

use function in_array;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final class IntervalField extends BaseField
{
    private const DEFAULT_INTERVAL = [
        'minutes' => [0],
        'hours' => [0],
        'days' => 'all',
        'weekdays' => 'all',
        'months' => 'all',
    ];

    /** @var array */
    private $intervalElements = [];

    public function setValue($value)
    {
        if (null === $value && [] === $this->intervalElements) {
            $value = self::DEFAULT_INTERVAL;
        } elseif (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        $this->intervalElements = $value;
        $this->value = json_encode($value);
    }

    /** @return array */
    public function getIntervalElements()
    {
        return $this->intervalElements;
    }

    public function getSaveValue(): string
    {
        $value = $this->intervalElements;

        $save = [];
        foreach (['minutes', 'hours', 'days', 'weekdays', 'months'] as $key) {
            if (!isset($value[$key])) {
                $save[$key] = [];
            } elseif ('all' === $value[$key]) {
                $save[$key] = 'all';
            } else {
                $save[$key] = array_map('intval', $value[$key]);
            }
        }

        return json_encode($save, JSON_THROW_ON_ERROR);
    }

    /** @return string */
    public function formatElement()
    {
        /** @return iterable<int, string> */
        $range = static function (int $low, int $high, int $step = 1): Generator {
            foreach (range($low, $high, $step) as $i) {
                yield $i => str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            }
        };

        $elements = [];

        $n = [];
        $n['label'] = '<label class="control-label">' . I18n::msg('cronjob_interval_minutes') . '</label>';
        $n['field'] = $this->formatField('minutes', I18n::msg('cronjob_interval_minutes_all'), $range(0, 55, 5));
        $elements[] = $n;

        $n = [];
        $n['label'] = '<label class="control-label">' . I18n::msg('cronjob_interval_hours') . '</label>';
        $n['field'] = $this->formatField('hours', I18n::msg('cronjob_interval_hours_all'), $range(0, 23));
        $elements[] = $n;

        $n = [];
        $n['label'] = '<label class="control-label">' . I18n::msg('cronjob_interval_days') . '</label>';
        $n['field'] = $this->formatField('days', I18n::msg('cronjob_interval_days_all'), $range(1, 31));
        $elements[] = $n;

        $n = [];
        $n['label'] = '<label class="control-label">' . I18n::msg('cronjob_interval_weekdays') . '</label>';
        $weekdays = static function () {
            for ($i = 1; $i < 7; ++$i) {
                yield $i => Formatter::intlDate(strtotime('last sunday +' . $i . ' days'), 'E');
            }
            yield 0 => Formatter::intlDate(strtotime('last sunday'), 'E');
        };
        $n['field'] = $this->formatField('weekdays', I18n::msg('cronjob_interval_weekdays_all'), $weekdays());
        $elements[] = $n;

        $n = [];
        $n['label'] = '<label class="control-label">' . I18n::msg('cronjob_interval_months') . '</label>';
        $months = static function () {
            for ($i = 1; $i < 13; ++$i) {
                yield $i => Formatter::intlDate(mktime(0, 0, 0, $i, 1), 'LLL');
            }
        };
        $n['field'] = $this->formatField('months', I18n::msg('cronjob_interval_months_all'), $months());
        $elements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $elements, false);
        $element = $fragment->parse('core/form/form.php');

        $element .= '
            <script type="text/javascript" nonce="' . Response::getNonce() . '">
            // <![CDATA[
                jQuery(function($){
                    $(".rex-js-cronjob-interval-all").each(function () {
                        var $this = $(this);
                        var $checkbox = $this.find(":checkbox");
                        var $particularBox = $this.next();
                        var update = function () {
                            var checked = $checkbox.is(":checked");
                            $particularBox.toggle(!checked);
                            $particularBox.find(":checkbox").prop("disabled", checked);
                        };
                        update(0);
                        $checkbox.change(update);
                    });
                });
            // ]]>
            </script>';

        return $element;
    }

    /**
     * @param string $group
     * @param string $optionAll
     * @param iterable<int, string> $options
     * @return string
     */
    private function formatField($group, $optionAll, $options)
    {
        $value = $this->intervalElements;
        $value = $value[$group] ?? [];

        $field = '<div class="rex-js-cronjob-interval-all rex-cronjob-interval-all">';

        $elements = [];

        $id = $this->getAttribute('id') . '-' . $group . '-all';
        $name = $this->getAttribute('name') . '[' . $group . ']';
        $checked = 'all' === $value ? ' checked="checked"' : '';

        $elements[] = [
            'label' => '<label class="control-label" for="' . escape($id) . '">' . $optionAll . '</label>',
            'field' => '<input type="checkbox" id="' . escape($id) . '" name="' . escape($name) . '" value="all"' . $checked . ' />',
        ];

        $fragment = new Fragment();
        $fragment->setVar('elements', $elements, false);
        $fragment->setVar('grouped', true);
        $field .= $fragment->parse('core/form/checkbox.php');

        $field .= '</div><div class="rex-js-cronjob-interval-particular rex-cronjob-interval-' . $group . '">';

        $elements = [];
        foreach ($options as $key => $label) {
            $id = $this->getAttribute('id') . '-' . $group . '-' . $key;
            $name = $this->getAttribute('name') . '[' . $group . '][]';
            $checked = is_array($value) && in_array($key, $value) ? ' checked="checked"' : '';

            $elements[] = [
                'label' => '<label class="control-label" for="' . escape($id) . '">' . $label . '</label>',
                'field' => '<input type="checkbox" id="' . escape($id) . '" name="' . escape($name) . '" value="' . $key . '"' . $checked . ' />',
            ];
        }

        $fragment = new Fragment();
        $fragment->setVar('elements', $elements, false);
        $fragment->setVar('grouped', true);
        $fragment->setVar('inline', true);
        $field .= $fragment->parse('core/form/checkbox.php');

        $field .= '</div>';

        return $field;
    }

    /** @return string */
    protected function getFragment()
    {
        return 'core/form/container.php';
    }
}
