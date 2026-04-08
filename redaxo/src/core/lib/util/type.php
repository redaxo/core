<?php

/**
 * Class for var casting.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_type
{
    /**
     * Casts the variable $var to $vartype.
     *
     * Possible types:
     *  - 'bool' (or 'boolean')
     *  - 'int' (or 'integer')
     *  - 'double'
     *  - 'string'
     *  - 'float'
     *  - 'real'
     *  - 'object'
     *  - 'array'
     *  - 'array[<type>]', e.g. 'array[int]'
     *  - '' (don't cast)
     *  - a callable
     *  - ['foo', 'bar', 'baz'] (cast to ony of the given values)
     *  - [
     *      [<key>, <vartype>, <default>],
     *      [<key>, <vartype>, <default>],
     *      ...
     *    ]
     *
     * @param mixed $var Variable to cast
     * @param string|callable(mixed):mixed|list<int|string|BackedEnum|null>|list<array{0: string, 1: string|callable(mixed):mixed|list<mixed>, 2?: mixed}> $vartype Variable type
     *
     * @throws InvalidArgumentException
     *
     * @return mixed Casted value
     *
     * @psalm-taint-specialize
     */
    public static function cast($var, $vartype)
    {
        if (is_string($vartype)) {
            switch ($vartype) {
                case 'bool':
                case 'boolean':
                    return (bool) $var;

                case 'int':
                case 'integer':
                    return (int) $var;

                case 'double':
                case 'float':
                case 'real':
                    return (float) $var;

                case 'string':
                    if (is_array($var)) { // https://github.com/redaxo/core/issues/2900
                        return '';
                    }
                    return (string) $var;

                case 'object':
                    return (object) $var;

                case 'array':
                    if ('' === $var) {
                        return [];
                    }
                    return (array) $var;

                case '':
                    // kein Cast, nichts tun
                    return $var;

                default:
                    // check for array with generic type
                    if (!str_starts_with($vartype, 'array[')) {
                        break;
                    }

                    if (empty($var)) {
                        $var = [];
                    } else {
                        $var = (array) $var;
                    }

                    // check if every element in the array is from the generic type
                    $matches = [];
                    if (!preg_match('@array\[([^\]]*)\]@', $vartype, $matches)) {
                        throw new InvalidArgumentException('Unexpected vartype "' . $vartype . '" in cast()!');
                    }

                    foreach ($var as $key => $value) {
                        try {
                            $var[$key] = self::cast($value, $matches[1]);
                        } catch (InvalidArgumentException) {
                            // Evtl Typo im vartype, mit urspr. typ als fehler melden
                            throw new InvalidArgumentException('Unexpected vartype "' . $vartype . '" in cast()!');
                        }
                    }

                    return $var;
            }
        }

        if (is_callable($vartype)) {
            return call_user_func($vartype, $var);
        }
        if (is_string($vartype)) {
            throw new InvalidArgumentException('Unexpected vartype "' . $vartype . '" in cast()!');
        }
        if (!is_array($vartype) || [] === $vartype) {
            throw new InvalidArgumentException('Unexpected vartype in cast()!');
        }

        $oneOf = false;
        $shape = false;
        foreach ($vartype as $cast) {
            if (is_array($cast)) {
                $shape = true;
            } elseif (is_scalar($cast) || null === $cast || $cast instanceof BackedEnum) {
                $oneOf = true;
            } else {
                throw new InvalidArgumentException('Unexpected vartype in cast()!');
            }
        }
        if ($oneOf && $shape) {
            throw new InvalidArgumentException('Unexpected vartype in cast()!');
        }

        if ($oneOf) {
            foreach ($vartype as $cast) {
                $castValue = $cast instanceof BackedEnum ? $cast->value : $cast;
                $castedVar = null === $cast ? $var : self::cast($var, gettype($castValue));
                if ($castedVar === $castValue) {
                    return $cast;
                }
            }
            return $vartype[array_key_first($vartype)];
        }

        $var = self::cast($var, 'array');
        $newVar = [];
        foreach ($vartype as $cast) {
            if (!is_array($cast) || !isset($cast[0])) {
                throw new InvalidArgumentException('Unexpected vartype in cast()!');
            }

            $key = $cast[0];
            $innerVartype = $cast[1] ?? '';
            $default = array_key_exists(2, $cast) ? $cast[2] : '';
            if (array_key_exists($key, $var)) {
                if (is_array($innerVartype) && '' !== $default && is_scalar($innerVartype[0] ?? null) && $innerVartype[0] !== $default) {
                    array_unshift($innerVartype, $default);
                }

                $newVar[$key] = self::cast($var[$key], $innerVartype);
            } elseif ('' === $default) {
                $newVar[$key] = self::cast('', $innerVartype);
            } else {
                $newVar[$key] = $cast[2];
            }
        }

        return $newVar;
    }

    /**
     * @template T
     * @param T|null $value
     * @return T
     * @psalm-assert !null $value
     * @psalm-pure
     */
    public static function notNull(mixed $value): mixed
    {
        if (null === $value) {
            throw new InvalidArgumentException('Exptected a value other than null');
        }

        return $value;
    }

    /**
     * @psalm-assert bool $value
     * @psalm-pure
     */
    public static function bool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Exptected a boolean, but got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @psalm-assert ?bool $value
     * @psalm-pure
     */
    public static function nullOrBool(mixed $value): ?bool
    {
        return null === $value ? null : self::bool($value);
    }

    /**
     * @param mixed $value
     * @psalm-assert string $value
     * @psalm-pure
     */
    public static function string($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Exptected a string, but got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @psalm-assert ?string $value
     * @psalm-pure
     */
    public static function nullOrString($value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    /**
     * @param mixed $value
     * @psalm-assert int $value
     * @psalm-pure
     */
    public static function int($value): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException('Exptected an integer, but got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @psalm-assert ?int $value
     * @psalm-pure
     */
    public static function nullOrInt($value): ?int
    {
        return null === $value ? null : self::int($value);
    }

    /**
     * @param mixed $value
     * @psalm-assert array $value
     * @psalm-pure
     */
    public static function array($value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Exptected an array, but got ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @template T of object
     * @param mixed $value
     * @param class-string<T> $class
     * @return T
     * @psalm-assert T $value
     * @psalm-pure
     */
    public static function instanceOf($value, string $class): object
    {
        if (!$value instanceof $class) {
            throw new InvalidArgumentException('Exptected a ' . $class . ', but got ' . get_debug_type($value));
        }

        return $value;
    }
}
