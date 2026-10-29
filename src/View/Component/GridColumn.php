<?php

namespace Redaxo\Core\View\Component;

use BackedEnum;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Column of a {@see Grid}, needed only for rules or an alignment; the grid takes plain content as a column, too.
 *
 * ```php
 * new GridColumn($aside, [
 *     new GridColumnRule(Breakpoint::Xs, order: GridOrder::First),
 *     new GridColumnRule(Breakpoint::Lg, width: 4, order: GridOrder::Source),
 * ], alignY: GridAlign::Start);
 * ```
 *
 * A {@see Grid} with `subgrid: true` as content becomes the column itself, so its columns use the tracks of the
 * outer grid.
 */
final readonly class GridColumn implements Renderable
{
    use RendersView;

    /** @var array<value-of<Breakpoint>, GridColumnRule> */
    public array $rules;

    /**
     * @param list<GridColumnRule> $rules at most one per breakpoint, in any order
     * @param HtmlAttributes $attributes further attributes, e.g. an `id`
     */
    public function __construct(
        public string|Renderable $content,
        array $rules = [],
        public GridAlign $alignX = GridAlign::Stretch,
        public GridAlign $alignY = GridAlign::Stretch,
        public HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        $sorted = [];
        foreach (Breakpoint::cases() as $breakpoint) {
            foreach ($rules as $rule) {
                if ($rule->breakpoint !== $breakpoint) {
                    continue;
                }
                if (isset($sorted[$breakpoint->value])) {
                    throw new InvalidArgumentException('A grid column has two rules for the breakpoint "' . $breakpoint->value . '".');
                }
                $sorted[$breakpoint->value] = $rule;
            }
        }
        $this->rules = $sorted;
    }

    /**
     * Value of a rule property per breakpoint, inherited from the smaller breakpoints.
     *
     * @template T
     * @param callable(GridColumnRule): (T|null) $value
     * @param T $default
     * @return array<value-of<Breakpoint>, T>
     * @internal
     */
    public function resolve(callable $value, mixed $default): array
    {
        $resolved = [];
        foreach (Breakpoint::cases() as $breakpoint) {
            $rule = $this->rules[$breakpoint->value] ?? null;
            $default = (null === $rule ? null : $value($rule)) ?? $default;
            $resolved[$breakpoint->value] = $default;
        }

        return $resolved;
    }

    /**
     * Space separated tokens of a rule property as in the `data-*` attributes, e.g. `first md:source`.
     *
     * @param callable(GridColumnRule): (int|BackedEnum|null) $value
     * @internal
     */
    public function tokens(callable $value): ?string
    {
        $tokens = [];
        foreach ($this->rules as $breakpoint => $rule) {
            $token = $value($rule);
            if (null === $token) {
                continue;
            }
            $token = $token instanceof BackedEnum ? (string) $token->value : (string) $token;
            $tokens[] = Breakpoint::Xs->value === $breakpoint ? $token : $breakpoint . ':' . $token;
        }

        return $tokens ? implode(' ', $tokens) : null;
    }
}
