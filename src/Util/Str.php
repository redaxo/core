<?php

namespace Redaxo\Core\Util;

use Normalizer;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Util\Exception\YamlParseException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use voku\helper\AntiXSS;

use function is_array;
use function is_int;
use function Redaxo\Core\View\escape;

/**
 * String utility class.
 *
 * @psalm-type TUrlParam = string|int|bool|array<string|int|bool|null>|null
 * @psalm-type TUrlParams = array<string, TUrlParam>
 */
final class Str
{
    private function __construct() {}

    /**
     * Returns the string size in bytes.
     *
     * @return int Size in bytes
     */
    public static function size(string $string): int
    {
        return mb_strlen($string, '8bit');
    }

    /**
     * Normalizes the encoding of a string (UTF8 NFD to NFC).
     *
     * On HFS+ filesystem (OS X) filenames are stored in UTF8 NFD while all other filesystems are
     * using UTF8 NFC. NFC is more common in general.
     */
    public static function normalizeEncoding(string $string): string
    {
        return Normalizer::normalize($string, Normalizer::FORM_C);
    }

    /**
     * Normalizes a string.
     *
     * Makes the string lowercase, replaces umlauts by their ascii representation (ä -> ae etc.), and replaces all
     * other chars that do not match a-z, 0-9 or $allowedChars by $replaceChar.
     *
     * @param string $replaceChar Character that is used to replace not allowed chars
     * @param string $allowedChars Allowed character list
     */
    public static function normalize(string $string, string $replaceChar = '_', string $allowedChars = ''): string
    {
        $string = self::normalizeEncoding($string);
        $string = mb_strtolower($string);
        $string = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $string);
        $string = preg_replace('/[^a-z\d' . preg_quote($allowedChars, '/') . ']+/ui', $replaceChar, $string);
        return trim($string, $replaceChar);
    }

    /**
     * Splits a string by spaces
     * (Strings with quotes will be regarded).
     *
     * Examples:
     * "a b 'c d'"   -> array('a', 'b', 'c d')
     * "a=1 b='c d'" -> array('a' => 1, 'b' => 'c d')
     *
     * @return array<string>
     */
    public static function split(string $string): array
    {
        $string = trim($string);
        if (empty($string)) {
            return [];
        }

        $result = [];
        $spacer = '@@@REX_SPACER@@@';
        $quoted = [];

        $pattern = '@(?<=\s|=|^)(["\'])((?:.*[^\\\\])?(?:\\\\\\\\)*)\\1(?=\s|$)@Us';
        $callback = static function (array $match) use ($spacer, &$quoted) {
            /** @var list<string> $match */
            $quoted[] = str_replace(['\\' . $match[1], '\\\\'], [$match[1], '\\'], $match[2]);
            return $spacer;
        };
        $string = preg_replace_callback($pattern, $callback, $string);

        $parts = preg_split('@\s+@', $string);
        $i = 0;
        foreach ($parts as $part) {
            $part = explode('=', $part, 2);
            if (isset($part[1])) {
                $value = $part[1] == $spacer ? $quoted[$i++] : $part[1];
                $result[$part[0]] = $value;
            } else {
                $value = $part[0] == $spacer ? $quoted[$i++] : $part[0];
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns a string containing the YAML representation of $value.
     *
     * @param array<mixed> $value The value being encoded
     * @param int $inline The level where you switch to inline YAML
     */
    public static function yamlEncode(array $value, int $inline = 3): string
    {
        return Yaml::dump($value, $inline, 4);
    }

    /**
     * Parses YAML into a PHP array.
     *
     * @param string $value YAML string
     *
     * @throws YamlParseException
     *
     * @return array<mixed>
     */
    public static function yamlDecode(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        try {
            $result = Yaml::parse($value, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $exception) {
            throw new YamlParseException($exception->getMessage(), $exception);
        }

        if (!is_array($result)) {
            throw new YamlParseException(__FUNCTION__ . ' does not support YAML content containing a single scalar value (given "' . $value . '")');
        }

        return $result;
    }

    /**
     * Generates URL-encoded query string.
     *
     * @param TUrlParams $params
     */
    public static function buildQuery(array $params): string
    {
        $query = [];
        $func = /** @param TUrlParams $params */ static function (array $params, ?string $fullkey = null) use (&$query, &$func) {
            foreach ($params as $key => $value) {
                $key = $fullkey ? $fullkey . '[' . urlencode($key) . ']' : urlencode($key);
                if (is_array($value)) {
                    $func($value, $key);
                } else {
                    $query[] = $key . '=' . str_replace('%2F', '/', urlencode((string) $value));
                }
            }
        };
        $func($params);
        return implode('&', $query);
    }

    /**
     * Returns a string by key="value" pair.
     *
     * @param array<int|string, int|string|list<string>> $attributes
     */
    public static function buildAttributes(array $attributes): string
    {
        $attr = '';

        foreach ($attributes as $key => $value) {
            if (is_int($key)) {
                if (is_array($value)) {
                    throw new InvalidArgumentException('For integer keys the value can not be an array.');
                }
                $attr .= ' ' . (string) escape($value);
            } else {
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }
                // for bc reasons avoid double escaping of "&", especially in already escaped urls
                $value = str_replace('&amp;', '&', (string) $value);
                $attr .= ' ' . escape($key) . '="' . escape($value) . '"';
            }
        }

        return $attr;
    }

    /** Highlights a string. */
    public static function highlight(string $string): string
    {
        $text = highlight_string($string, true);

        if (str_starts_with($text, '<pre>')) {
            $text = substr($text, 5, -6);
        }

        // php 8.3 fix
        $text = preg_replace('@<code style="color:[^"]+">@', '<code>', $text, 1);
        $text = preg_replace('@<span style="color:[^"]+">\n(<span style="color:[^"]+">)@', '$1', $text, 1);
        $text = preg_replace('@<\/span>\n(<\/span>\n<\/code>)$@', '$1', $text, 1);

        return '<pre class="rex-code">' . $text . '</pre>';
    }

    /** Cleanup the given html string and removes possible malicious codes/markup. */
    public static function sanitizeHtml(string $html): string
    {
        /** @var AntiXSS|null $antiXss */
        static $antiXss;

        if (!$antiXss) {
            $antiXss = new AntiXSS();
            $antiXss->removeEvilAttributes(['style']);
            $antiXss->removeNeverAllowedRegex(['(\(?:?document\)?|\(?:?window\)?(?:\.document)?)\.(?:location|on\w*)' => '']);
            $antiXss->removeNeverAllowedStrAfterwards(['&lt;script&gt;', '&lt;/script&gt;']);
        }

        /** @psalm-taint-escape html */
        return $antiXss->xss_clean($html);
    }
}
