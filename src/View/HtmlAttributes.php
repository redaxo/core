<?php

namespace Redaxo\Core\View;

use BackedEnum;
use Redaxo\Core\Util\Type;
use Stringable;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Immutable container for HTML attributes.
 *
 * ```php
 * $attributes = new HtmlAttributes([
 *     'attr1' => 'my_value', // string value
 *     'attr2' => 5, // integer value
 *     'attr3' => $myEnum, // BackedEnum values
 *     'attr4' => null, // attributes with `null`/`false` value are omitted
 *     'disabled' => true, // `true` value for boolean attributes without value
 *     'class' => [ // arrays for space separated attributes
 *         'cls1',
 *         'cls2',
 *         'cls3' => false, // conditional classes
 *         'cls4' => true,  // conditional classes
 *     ],
 * ]);
 * $attributes->toString();
 * ```
 *
 * The example will result in this attributes string:
 *    ` attr1="my_value" attr2="5" attr3="my_enum_value" disabled class="cls1 cls2 cls4"`
 *
 * The object is immutable: {@see with()} returns a new instance. Build attributes up by collecting
 * them in a plain `array` (mutable, conditional logic is cheap) and passing it once to the
 * constructor or {@see with()}, instead of mutating the object step by step.
 */
final readonly class HtmlAttributes implements Stringable
{
    public function __construct(
        /** @var array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null> */
        private array $attributes = [],
    ) {}

    /**
     * Returns a new instance in which the given attributes take precedence over the existing ones.
     *
     * The attributes passed here win: a scalar value replaces whatever the existing side held for
     * that key and cannot be overridden from there. An array value (e.g. `class`) is merged instead
     * — existing parts are appended while the on/off state declared here is kept. This serves two
     * purposes: setting/overriding values while building up attributes, and declaring component
     * defaults in a view that callers must not override.
     *
     * Note: merging only happens when the value passed here is an array. A string like
     * `'class' => 'foo'` is treated as a fixed, non-extendable value — write `'class' => ['foo']`
     * if the other side should be able to add further classes.
     *
     * @param array<literal-string, bool|string|int|BackedEnum|array<string|int, string|bool>|list<BackedEnum>|null> $attributes
     */
    public function with(array $attributes): self
    {
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $attributes)) {
                $attributes[$key] = $value;
                continue;
            }

            if (!is_array($attributes[$key])) {
                // A scalar value passed to with() wins and stays fixed: the existing value for this
                // key is dropped and cannot override it. Only array values (e.g. `class`) are merged.
                continue;
            }

            if (is_array($value)) {
                $value = self::normalizeArrayValue($value);
            } elseif (is_string($value)) {
                $value = self::normalizeArrayValue(explode(' ', $value));
            } elseif ($value instanceof BackedEnum) {
                $value = [Type::string($value->value) => true];
            } else {
                continue;
            }

            $normalized = self::normalizeArrayValue($attributes[$key]);

            foreach ($value as $part => $enabled) {
                if (!$enabled) {
                    continue;
                }
                if (isset($normalized[$part])) {
                    continue;
                }

                $attributes[$key][] = $part;
            }
        }

        return new self($attributes);
    }

    public function toString(): string
    {
        $attr = '';

        foreach ($this->attributes as $key => $value) {
            if (true === $value) {
                $attr .= ' ' . $key;

                continue;
            }

            if (is_array($value)) {
                $value = self::arrayValue($value);
            }

            if (null === $value || false === $value) {
                continue;
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (is_string($value)) {
                $value = escape($value);
            }

            $attr .= ' ' . $key . '="' . $value . '"';
        }

        return ltrim($attr);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** @param array<string|int, string|bool>|list<BackedEnum> $array */
    private static function arrayValue(array $array): ?string
    {
        $array = self::normalizeArrayValue($array);

        if (!$array) {
            return null;
        }

        return implode(' ', array_keys(array_filter($array)));
    }

    /**
     * @param array<string|int, string|bool>|list<BackedEnum> $array
     * @return array<string, bool>
     */
    private static function normalizeArrayValue(array $array): array
    {
        $normalized = [];

        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = Type::bool($value);
                continue;
            }

            $value = $value instanceof BackedEnum ? $value->value : $value;
            $normalized[Type::string($value)] = true;
        }

        return $normalized;
    }
}
