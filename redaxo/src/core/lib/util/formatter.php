<?php

/**
 * String formatter class.
 *
 * @package redaxo\core
 */
abstract class rex_formatter
{
    /** It's not allowed to create instances of this class. */
    private function __construct() {}

    /**
     * Formats a string by the given format type.
     *
     * @param string $value Value
     * @param string $formatType Format type (any method name of this class)
     * @param mixed $format For possible values look at the other methods of this class
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public static function format($value, $formatType, $format)
    {
        if (!is_callable([self::class, $formatType])) {
            throw new InvalidArgumentException('Unknown $formatType: "' . $formatType . '"!');
        }
        return self::$formatType($value, $format);
    }

    /**
     * Formats a string by `date()`.
     *
     * @see https://www.php.net/manual/en/function.date.php
     *
     * @param string|int|null $value Unix timestamp or datetime string for `strtotime`
     * @param string $format Default format is `d.m.Y`
     *
     * @return string
     */
    public static function date($value, $format = '')
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = self::getTimestamp($value);

        if (null === $timestamp) {
            return '';
        }

        if ('' == $format) {
            $format = 'd.m.Y';
        }

        return date($format, $timestamp);
    }

    /**
     * Formats a string by `strftime()`.
     *
     * @see https://www.php.net/manual/en/function.strftime.php
     *
     * @param string|int|null $value Unix timestamp or datetime string for `strtotime`
     * @param string $format Possible values are format strings like in `strftime` or "date" or "datetime", default is "date"
     *
     * @return string
     *
     * @deprecated since 5.13.0
     */
    public static function strftime($value, $format = '')
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = self::getTimestamp($value);

        if (null === $timestamp) {
            return '';
        }

        if ('' === $format || 'date' === $format) {
            return self::intlDate($timestamp);
        }
        if ('datetime' === $format) {
            return self::intlDateTime($timestamp);
        }
        if ('time' === $format) {
            return self::intlTime($timestamp);
        }

        if (function_exists('strftime')) {
            return strftime($format, $timestamp);
        }

        // strftime does not exist anymore, return unformatted datetime string
        if (is_int($value) || ctype_digit($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        return $value;
    }

    /**
     * Formats a datetime by `IntlDateFormmater`.
     *
     * @see https://www.php.net/manual/en/class.intldateformatter.php
     *
     * @param string|int|DateTimeInterface|null $value Unix timestamp, datetime string for `strtotime` or DateTimeInterface object
     * @param IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|array{0: IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|IntlDateFormatter::NONE, 1: IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|IntlDateFormatter::NONE}|string|null $format
     *              Possible format values:
     *                  - `IntlDateFormatter` constant, like `IntlDateFormatter::MEDIUM`
     *                  - array with two `IntlDateFormatter` constants for date format and time format, like `[IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]`
     *                  - string pattern, like `dd.MM.y`
     *              Defaults to `[IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]`
     */
    public static function intlDateTime($value, $format = null): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $timeZone = $value->getTimezone()->getName();
        } else {
            $value = self::getTimestamp($value);

            if (null === $value) {
                return '';
            }

            $timeZone = date_default_timezone_get();
        }

        if (null === $format || '' === $format) {
            $format = [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT];
        }

        if (is_string($format)) {
            $pattern = $format;
            $dateFormat = $timeFormat = IntlDateFormatter::NONE;
        } elseif (is_array($format)) {
            $pattern = '';
            [$dateFormat, $timeFormat] = $format;
        } else {
            $pattern = '';
            $dateFormat = $timeFormat = $format;
        }

        $cacheKey = $pattern . '-' . $dateFormat . '-' . $timeFormat;
        $locale = Locale::getDefault();

        /** @var array<string, array<string, array<string, IntlDateFormatter>>> */
        static $formatters = [];

        $formatter = $formatters[$locale][$timeZone][$cacheKey] ?? null;

        if (!$formatter) {
            $formatter = new IntlDateFormatter($locale, $dateFormat, $timeFormat, IntlTimeZone::createTimeZone($timeZone), null, $pattern);

            switch ($dateFormat) {
                case IntlDateFormatter::SHORT:
                    // Avoid two-digit year format, which is used for some languages in short date format
                    $formatter->setPattern(str_replace(['yyyy', 'yy'], 'y', $formatter->getPattern()));
                    break;
                case IntlDateFormatter::MEDIUM:
                    // Change german medium date format to "2. Sep. 2020", which is more similar to the medium format of other languages
                    if ('d' === $locale[0] && 'e' === $locale[1]) {
                        $formatter->setPattern(str_replace('dd.MM.y', 'd. LLL. y', $formatter->getPattern()));
                    }
                    break;
            }

            $formatters[$locale][$timeZone][$cacheKey] = $formatter;
        }

        return $formatter->format($value);
    }

    /**
     * Formats a date by `IntlDateFormmater`.
     *
     * @see https://www.php.net/manual/en/class.intldateformatter.php
     *
     * @param string|int|DateTimeInterface|null $value Unix timestamp, date string for `strtotime` or DateTimeInterface object
     * @param IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|string|null $format
     *              Possible format values:
     *                  - `IntlDateFormatter` constant, like `IntlDateFormatter::MEDIUM`
     *                  - string pattern, like `dd.MM.y`
     *              Defaults to `IntlDateFormatter::MEDIUM`
     */
    public static function intlDate($value, $format = null): string
    {
        if (null === $format || '' === $format) {
            $format = IntlDateFormatter::MEDIUM;
        }

        return self::intlDateTime($value, is_string($format) ? $format : [$format, IntlDateFormatter::NONE]);
    }

    /**
     * Formats a time by `IntlDateFormmater`.
     *
     * @see https://www.php.net/manual/en/class.intldateformatter.php
     *
     * @param string|int|DateTimeInterface|null $value Unix timestamp, time string for `strtotime` or DateTimeInterface object
     * @param IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|string|null $format
     *              Possible format values:
     *                  - `IntlDateFormatter` constant, like `IntlDateFormatter::MEDIUM`
     *                  - string pattern, like `HH:mm`
     *              Defaults to `IntlDateFormatter::SHORT`
     */
    public static function intlTime($value, $format = IntlDateFormatter::SHORT): string
    {
        if (null === $format || '' === $format) {
            $format = IntlDateFormatter::SHORT;
        }

        return self::intlDateTime($value, is_string($format) ? $format : [IntlDateFormatter::NONE, $format]);
    }

    /**
     * Formats a string by `number_format()`.
     *
     * @see https://www.php.net/manual/en/function.number-format.php
     *
     * @param string|float $value Value
     * @param array $format Array with number of decimals, decimals point and thousands separator, default is `array(2, ',', ' ')`
     *
     * @return string
     */
    public static function number($value, $format = [])
    {
        if (!is_array($format)) {
            $format = [];
        }

        // Kommastellen
        if (!isset($format[0])) {
            $format[0] = 2;
        }
        // Dezimal Trennzeichen
        if (!isset($format[1])) {
            $format[1] = ',';
        }
        // Tausender Trennzeichen
        if (!isset($format[2])) {
            $format[2] = ' ';
        }
        return number_format((float) $value, $format[0], $format[1], $format[2]);
    }

    /**
     * Formats a string as bytes.
     *
     * @param string|int $value Value
     * @param array $format Same as {@link rex_formatter::number()}
     *
     * @return string
     */
    public static function bytes($value, $format = [])
    {
        $value = (int) $value;

        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $unitIndex = 0;
        while (($value / 1024) >= 1) {
            $value /= 1024;
            ++$unitIndex;
        }

        if (isset($format[0])) {
            $precision = (int) $format[0];
            $z = (int) ($value * 10 ** $precision);
            for ($i = 0; $i < $precision; ++$i) {
                if (0 == ($z % 10)) {
                    $format[0] = (int) $format[0] - 1;
                    $z = (int) ($z / 10);
                } else {
                    break;
                }
            }
        }

        return self::number($value, $format) . ' ' . $units[$unitIndex];
    }

    /**
     * Formats a string by `sprintf()`.
     *
     * @see https://www.php.net/manual/en/function.sprintf.php
     *
     * @param string $value Value
     * @param string $format
     *
     * @return string
     */
    public static function sprintf($value, $format = '')
    {
        if ('' == $format) {
            $format = '%s';
        }
        return sprintf($format, $value);
    }

    /**
     * Formats a string by `nl2br`.
     *
     * @see https://www.php.net/manual/en/function.nl2br.php
     *
     * @param string $value Value
     *
     * @return string
     */
    public static function nl2br($value)
    {
        return nl2br($value);
    }

    /**
     * Truncates a string.
     *
     * @param string $value Value
     * @param array{length?: int, etc?: string, break_words?: bool} $format Default format is `['length' => 80, 'etc' => '…', 'break_words' => false]`
     *
     * @return string
     */
    public static function truncate($value, $format = [])
    {
        if (!is_array($format)) {
            $format = [];
        }

        // Max-String-laenge
        if (empty($format['length'])) {
            $format['length'] = 80;
        }

        // ETC
        if (empty($format['etc'])) {
            $format['etc'] = '…';
        }

        // Break-Words?
        if (empty($format['break_words'])) {
            $format['break_words'] = false;
        }

        if (mb_strlen($value) > $format['length']) {
            $format['length'] -= mb_strlen($format['etc']);
            if (!$format['break_words']) {
                $value = preg_replace('/\s+?(\S+)?$/', '', substr($value, 0, $format['length'] + 1));
            }

            return substr($value, 0, $format['length']) . $format['etc'];
        }

        return $value;
    }

    /**
     * Avoid widows in a string.
     *
     * @param string $value
     *
     * @return string
     */
    public static function widont($value)
    {
        // Sollte ein Wort allein auf einer Zeile vorkommen, wird dies unterbunden
        $value = rtrim($value);
        $space = strrpos($value, ' ');
        if (false !== $space) {
            $value = substr($value, 0, $space) . '&#160;' . substr($value, $space + 1);
        }
        return $value;
    }

    /**
     * Formats a version string by `sprintf()`.
     *
     * @see https://www.php.net/manual/en/function.sprintf.php
     *
     * @param string $value Version
     * @param string $format Version format, e.g. "%s.%s"
     *
     * @return string
     */
    public static function version($value, $format)
    {
        return vsprintf($format, rex_version::split($value));
    }

    /**
     * Formats a string as link.
     *
     * @param string $value URL
     * @param array $format Array with link attributes and params
     *
     * @return string Link
     */
    public static function url($value, $format = [])
    {
        if (empty($value)) {
            return '';
        }

        if (!is_array($format)) {
            $format = [];
        }

        // Linkattribute
        if (empty($format['attr'])) {
            $format['attr'] = '';
        }
        // Linkparameter (z.b. subject=Hallo Sir)
        if (empty($format['params'])) {
            $format['params'] = '';
        } else {
            if (strstr($format['params'], '?') != $format['params']) {
                $format['params'] = '?' . $format['params'];
            }
        }
        // Protokoll
        if (!preg_match('@((ht|f)tps?|telnet|redaxo)://@', $value)) {
            $value = 'http://' . $value;
        }

        return '<a href="' . rex_escape($value . $format['params']) . '"' . $format['attr'] . '>' . rex_escape($value) . '</a>';
    }

    /**
     * Formats a string as email link.
     *
     * @param string $value Email
     * @param array $format Array with link attributes and params
     *
     * @return string Email link
     */
    public static function email($value, $format = [])
    {
        if (!is_array($format)) {
            $format = [];
        }

        // Linkattribute
        if (empty($format['attr'])) {
            $format['attr'] = '';
        }
        // Linkparameter (z.b. subject=Hallo Sir)
        if (empty($format['params'])) {
            $format['params'] = '';
        } else {
            if (strstr($format['params'], '?') != $format['params']) {
                $format['params'] = '?' . $format['params'];
            }
        }
        // Url formatierung
        return '<a href="mailto:' . rex_escape($value . $format['params']) . '"' . $format['attr'] . '>' . rex_escape($value) . '</a>';
    }

    /**
     * Formats a string by a custom callable.
     *
     * @param string $value Value
     * @param callable(string):string|array{0: callable(non-empty-array):string, 1: mixed} $format A callable or an array of a callable and additional params
     *
     * @throws rex_exception
     *
     * @return string
     */
    public static function custom($value, $format)
    {
        if (!is_callable($format)) {
            if (!is_callable($format[0])) {
                throw new rex_exception('Unable to find callable ' . $format[0] . ' for custom format!');
            }

            $params = [];
            $params['subject'] = $value;
            if (is_array($format[1])) {
                $params = array_merge($format[1], $params);
            } else {
                $params['params'] = $format[1];
            }
            // $format ist in der Form
            // array(Name des Callables, Weitere Parameter)
            return call_user_func($format[0], $params);
        }

        return call_user_func($format, $value);
    }

    /**
     * Returns a Unix-Timestamp representing the given $value.
     *
     * Note: on a 32-bit php-version Unix-Timestamps cannot express
     * dates before 13 December 1901 or after 19 January 2038
     *
     * @see https://en.m.wikipedia.org/wiki/Unix_time
     * @see https://en.m.wikipedia.org/wiki/Year_2038_problem
     *
     * @param string|int $value
     *
     * @return int|null
     */
    private static function getTimestamp($value)
    {
        if (is_int($value) || ctype_digit($value)) {
            return (int) $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('$value must be a unix timestamp as int or a date(time) string, but "' . get_debug_type($value) . '" given');
        }

        if (str_starts_with($value, '0000-00-00')) {
            trigger_error(sprintf('%s: "%s" is not a valid datetime string.', __METHOD__, $value), E_USER_WARNING);

            return null;
        }

        $time = strtotime($value);

        if (false !== $time) {
            return $time;
        }

        throw new InvalidArgumentException(sprintf('"%s" is not a valid datetime string.', $value));
    }
}
