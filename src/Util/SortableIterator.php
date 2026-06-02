<?php

namespace Redaxo\Core\Util;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use Override;
use Redaxo\Core\Exception\LogicException;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 */
final readonly class SortableIterator implements IteratorAggregate
{
    public const int VALUES = 1;
    public const int KEYS = 2;

    /**
     * @param Traversable<TKey, TValue> $iterator Inner iterator
     * @param self::VALUES|self::KEYS|Closure(mixed,mixed):int $sort Sort mode, possible values are SortableIterator::VALUES (default), SortableIterator::KEYS or a callable
     */
    public function __construct(
        private Traversable $iterator,
        private int|Closure $sort = self::VALUES,
    ) {}

    #[Override]
    public function getIterator(): Traversable
    {
        $array = iterator_to_array($this->iterator);
        $normalize = static function (string $string): string {
            $string = preg_replace("/(?<=[aou])\xcc\x88/i", '', $string);
            $string = mb_strtolower($string);
            return str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 's'], $string);
        };
        $sortCallback = static function (mixed $a, mixed $b) use ($normalize): int {
            $a = $normalize((string) $a);
            $b = $normalize((string) $b);
            return strnatcasecmp($a, $b);
        };
        match (true) {
            self::VALUES === $this->sort => uasort($array, $sortCallback),
            self::KEYS === $this->sort => uksort($array, $sortCallback),
            $this->sort instanceof Closure => uasort($array, $this->sort),
            default => throw new LogicException('Unknown sort mode.'),
        };
        return new ArrayIterator($array);
    }
}
