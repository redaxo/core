<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Exception\InvalidArgumentException;
use stdClass;
use Stringable;

use function is_array;
use function is_string;
use function ord;
use function sprintf;
use function strlen;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Escapes a variable to be used while rendering html.
 *
 * This function is adapted from code coming from Twig.
 * (c) Fabien Potencier
 * https://github.com/twigphp/Twig/blob/1680bba4228c5dc3c9427fe82423300da8a9d487/src/Runtime/EscaperRuntime.php#L100
 *
 * @template T
 * @param T $value The value to escape
 * @param 'html'|'html_simplified'|'html_attr'|'js'|'css'|'url' $strategy Supported strategies:
 *                         "html": escapes a string for the HTML context.
 *                         "html_simplified": escapes a string for the HTML context. Allows some basic tags which are safe regarding XSS.
 *                         "html_attr": escapes a string for the HTML attrubute context. It is only necessary for dynamic attribute names and attribute values without quotes (`data-foo=bar`). For attribute values within quotes you can use default strategy "html".
 *                         "js": escapes a string for the JavaScript/JSON context.
 *                         "css": escapes a string for the CSS context. CSS escaping can be applied to any string being inserted into CSS and escapes everything except alphanumerics.
 *                         "url": escapes a string for the URI or parameter contexts. This should not be used to escape an entire URI; only a subcomponent being inserted.
 *
 * @return (T is Stringable ? string : T)
 *
 * @psalm-taint-escape has_quotes
 * @psalm-taint-escape html
 * @psalm-pure
 */
function escape(mixed $value, string $strategy = 'html'): mixed
{
    if (!is_string($value)) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = escape($v, $strategy);
            }

            return $value;
        }

        if ($value instanceof stdClass) {
            $clone = clone $value;
            foreach (get_object_vars($value) as $k => $v) { // @phpstan-ignore impure.functionCall
                $clone->$k = escape($v, $strategy); // @phpstan-ignore impure.propertyAssign
            }

            return $clone;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        } else {
            return $value;
        }
    }

    $string = $value;

    if ('' === $string) {
        return '';
    }

    switch ($strategy) {
        case 'html':
            // see https://www.php.net/htmlspecialchars
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        case 'html_simplified':
            $string = escape($string, 'html');
            return preg_replace('@&lt;(/?(?:b|i|code|kbd|var)|br ?/?)&gt;@i', '<$1>', $string);

        case 'js':
            // escape all non-alphanumeric characters
            // into their \x or \uHHHH representations

            if (!preg_match('//u', $string)) {
                throw new InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
            }

            $string = preg_replace_callback('#[^a-zA-Z0-9,\._]#Su', static function ($matches) {
                $char = $matches[0];

                /*
                 * A few characters have short escape sequences in JSON and JavaScript.
                 * Escape sequences supported only by JavaScript, not JSON, are omitted.
                 * \" is also supported but omitted, because the resulting string is not HTML safe.
                 */
                $short = match ($char) {
                    '\\' => '\\\\',
                    '/' => '\\/',
                    "\x08" => '\b',
                    "\x0C" => '\f',
                    "\x0A" => '\n',
                    "\x0D" => '\r',
                    "\x09" => '\t',
                    default => false,
                };

                if ($short) {
                    return $short;
                }

                $codepoint = mb_ord($char, 'UTF-8');
                if (0x1_00_00 > $codepoint) {
                    return sprintf('\u%04X', $codepoint);
                }

                // Split characters outside the BMP into surrogate pairs
                // https://tools.ietf.org/html/rfc2781.html#section-2.1
                $u = $codepoint - 0x1_00_00;
                $high = 0xD8_00 | ($u >> 10);
                $low = 0xDC_00 | ($u & 0x3_FF);

                return sprintf('\u%04X\u%04X', $high, $low);
            }, $string);

            return $string;

        case 'css':
            if (!preg_match('//u', $string)) {
                throw new InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
            }

            $string = preg_replace_callback('#[^a-zA-Z0-9]#Su', static function ($matches) {
                $char = $matches[0];

                return sprintf('\\%X ', 1 === strlen($char) ? ord($char) : mb_ord($char, 'UTF-8'));
            }, $string);

            return $string;

        case 'html_attr':
            if (!preg_match('//u', $string)) {
                throw new InvalidArgumentException('The string to escape is not a valid UTF-8 string.');
            }

            $string = preg_replace_callback('#[^a-zA-Z0-9,\.\-_]#Su', static function ($matches) {
                /**
                 * This function is adapted from code coming from Zend Framework.
                 *
                 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (https://www.zend.com)
                 * @license   https://framework.zend.com/license/new-bsd New BSD License
                 */
                $chr = $matches[0];
                $ord = ord($chr[0]);

                /*
                 * The following replaces characters undefined in HTML with the
                 * hex entity for the Unicode replacement character.
                 */
                if (($ord <= 0x1F && "\t" != $chr && "\n" != $chr && "\r" != $chr) || ($ord >= 0x7F && $ord <= 0x9F)) {
                    return '&#xFFFD;';
                }

                /*
                 * Check if the current character to escape has a name entity we should
                 * replace it with while grabbing the hex value of the character.
                 */
                if (1 === strlen($chr)) {
                    /*
                     * While HTML supports far more named entities, the lowest common denominator
                     * has become HTML5's XML Serialisation which is restricted to the those named
                     * entities that XML supports. Using HTML entities would result in this error:
                     *     XML Parsing Error: undefined entity
                     */
                    return match ($ord) {
                        34 => '&quot;', /* quotation mark */
                        38 => '&amp;',  /* ampersand */
                        60 => '&lt;',   /* less-than sign */
                        62 => '&gt;',   /* greater-than sign */
                        default => sprintf('&#x%02X;', $ord),
                    };
                }

                /*
                 * Per OWASP recommendations, we'll use hex entities for any other
                 * characters where a named entity does not exist.
                 */
                return sprintf('&#x%04X;', mb_ord($chr, 'UTF-8'));
            }, $string);

            return $string;

        case 'url':
            return rawurlencode($string);

        default:
            throw new InvalidArgumentException(sprintf('Invalid escaping strategy "%s" (valid ones: "html", "html_attr", "html_simplified", "css", "js", "url").', $strategy));
    }
}
