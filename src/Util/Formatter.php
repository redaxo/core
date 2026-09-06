<?php

namespace Redaxo\Core\Util;

use DateTimeInterface;
use IntlDateFormatter;
use IntlTimeZone;
use Locale;
use Redaxo\Core\Exception\InvalidArgumentException;

use function call_user_func;
use function count;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function Redaxo\Core\View\escape;
use function sprintf;
use function strlen;

use const E_USER_WARNING;

/**
 * String formatter class.
 */
final class Formatter
{
    /** It's not allowed to create instances of this class. */
    private function __construct() {}

    /**
     * Formats a string by the given format type.
     *
     * @param string $formatType Format type (any method name of this class)
     * @param mixed $format For possible values look at the other methods of this class
     */
    public static function format(?string $value, string $formatType, mixed $format): string
    {
        if (!method_exists(self::class, $formatType)) {
            throw new InvalidArgumentException('Unknown $formatType "' . $formatType . '".');
        }

        return Type::string(self::$formatType($value, $format));
    }

    /**
     * Formats a string by `date()`.
     *
     * @see https://www.php.net/manual/en/function.date.php
     *
     * @param int|string|null $value Unix timestamp or datetime string for `strtotime`
     * @param string $format Default format is `d.m.Y`
     */
    public static function date(int|string|null $value, ?string $format = null): string
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = self::getTimestamp($value);

        if (null === $timestamp) {
            return '';
        }

        $format ??= 'd.m.Y';

        return date($format, $timestamp);
    }

    /**
     * Formats a datetime by `IntlDateFormmater`.
     *
     * @see https://www.php.net/manual/en/class.intldateformatter.php
     *
     * @param string|int|DateTimeInterface|null $value Unix timestamp, datetime string for `strtotime` or DateTimeInterface object
     * @param IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|list{IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|IntlDateFormatter::NONE, IntlDateFormatter::FULL|IntlDateFormatter::LONG|IntlDateFormatter::MEDIUM|IntlDateFormatter::SHORT|IntlDateFormatter::NONE}|string|null $format
     *              Possible format values:
     *                  - `IntlDateFormatter` constant, like `IntlDateFormatter::MEDIUM`
     *                  - array with two `IntlDateFormatter` constants for date format and time format, like `[IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]`
     *                  - string pattern, like `dd.MM.y`
     *              Defaults to `[IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]`
     */
    public static function intlDateTime(string|int|DateTimeInterface|null $value, int|array|string|null $format = null): string
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

        $format ??= [IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT];

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
    public static function intlDate(string|int|DateTimeInterface|null $value, int|string|null $format = null): string
    {
        $format ??= IntlDateFormatter::MEDIUM;

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
    public static function intlTime(string|int|DateTimeInterface|null $value, int|string|null $format = IntlDateFormatter::SHORT): string
    {
        $format ??= IntlDateFormatter::SHORT;

        return self::intlDateTime($value, is_string($format) ? $format : [IntlDateFormatter::NONE, $format]);
    }

    /**
     * Formats a string by `number_format()`.
     *
     * @see https://www.php.net/manual/en/function.number-format.php
     *
     * @param array{0?: int, 1?: string, 2?: string}|null $format Array with number of decimals, decimals point and thousands separator, default is `array(2, ',', ' ')`
     */
    public static function number(string|float $value, ?array $format = []): string
    {
        $format ??= [];

        // Kommastellen
        $format[0] ??= 2;

        // Dezimal-Trennzeichen
        $format[1] ??= ',';

        // Tausender-Trennzeichen
        $format[2] ??= ' ';

        return number_format((float) $value, $format[0], $format[1], $format[2]);
    }

    /**
     * Formats a string as bytes.
     *
     * @param array{0?: int, 1?: string, 2?: string}|null $format Same as {@link self::number()}
     */
    public static function bytes(string|int $value, ?array $format = []): string
    {
        if (is_string($value)) {
            $value = trim($value);
            $last = strtolower($value[strlen($value) - 1] ?? '');
            $value = (int) $value;
            switch ($last) {
                case 'g':
                    $value *= 1024;
                    // no break
                case 'm':
                    $value *= 1024;
                    // no break
                case 'k':
                    $value *= 1024;
            }
        }

        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $unit = $units[0];
        $max = count($units) - 1;
        $unitIndex = 0;
        while (($value / 1024) >= 1) {
            ++$unitIndex;

            if ($unitIndex > $max) {
                break;
            }

            $value /= 1024;
            $unit = $units[$unitIndex];
        }

        $format ??= [];
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

        return self::number($value, $format) . ' ' . $unit;
    }

    /**
     * Formats a string by `sprintf()`.
     *
     * @see https://www.php.net/manual/en/function.sprintf.php
     */
    public static function sprintf(string $value, ?string $format = null): string
    {
        return sprintf($format ?? '%s', $value);
    }

    /**
     * Formats a string by `nl2br`.
     *
     * @see https://www.php.net/manual/en/function.nl2br.php
     */
    public static function nl2br(string $value): string
    {
        return nl2br($value);
    }

    /**
     * Truncates a string.
     *
     * @param string $value Value
     * @param array{length?: int, etc?: string, break_words?: bool}|null $format Default format is `['length' => 80, 'etc' => '…', 'break_words' => false]`
     */
    public static function truncate(string $value, ?array $format = []): string
    {
        $format ??= [];

        // Max-String-laenge
        $format['length'] ??= 80;

        // ETC
        $format['etc'] ??= '…';

        // Break-Words?
        $format['break_words'] ??= false;

        if (mb_strlen($value) > $format['length']) {
            $format['length'] -= mb_strlen($format['etc']);
            if (!$format['break_words']) {
                $value = preg_replace('/\s+?(\S+)?$/', '', substr($value, 0, $format['length'] + 1));
            }

            return substr($value, 0, $format['length']) . $format['etc'];
        }

        return $value;
    }

    /** Avoid widows in a string. */
    public static function widont(string $value): string
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
     * @param string|null $format Version format, e.g. "%s.%s"
     */
    public static function version(string $value, ?string $format): string
    {
        return vsprintf($format ?? '%s.%s.%s', Version::split($value));
    }

    /**
     * Formats a string as link.
     *
     * @param string $value URL
     * @param array{attr?: string, params?: string}|null $format Array with link attributes and params
     *
     * @return string Link
     */
    public static function url(string $value, ?array $format = []): string
    {
        if (empty($value)) {
            return '';
        }

        $format ??= [];

        // Linkattribute
        $format['attr'] ??= '';

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
            $value = 'https://' . $value;
        }

        return '<a href="' . escape($value . $format['params']) . '"' . $format['attr'] . '>' . escape($value) . '</a>';
    }

    /**
     * Formats a string as email link.
     *
     * @param string $value Email
     * @param array{attr?: string, params?: string}|null $format Array with link attributes and params
     *
     * @return string Email link
     */
    public static function email(string $value, ?array $format = []): string
    {
        $format ??= [];

        // Linkattribute
        $format['attr'] ??= '';

        // Linkparameter (z.b. subject=Hallo Sir)
        if (empty($format['params'])) {
            $format['params'] = '';
        } else {
            if (strstr($format['params'], '?') != $format['params']) {
                $format['params'] = '?' . $format['params'];
            }
        }
        // Url formatierung
        return '<a href="mailto:' . escape($value . $format['params']) . '"' . $format['attr'] . '>' . escape($value) . '</a>';
    }

    /**
     * Formats a string by a custom callable.
     *
     * @param (callable(string|null):string)|array{0: callable(non-empty-array<mixed>):string, 1: array<mixed>} $format A callable or an array of a callable and additional params
     */
    public static function custom(?string $value, callable|array $format): string
    {
        if (!is_callable($format)) {
            if (!is_callable($format[0])) {
                throw new InvalidArgumentException('Unable to find callable ' . $format[0] . ' for custom format!');
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
     */
    private static function getTimestamp(string|int $value): ?int
    {
        if (is_int($value) || ctype_digit($value)) {
            return (int) $value;
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
