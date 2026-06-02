<?php

namespace Redaxo\Core\ExtensionPoint;

use Redaxo\Core\Exception\LogicException;

/**
 * @template T
 *
 * @psalm-taint-specialize
 */
class ExtensionPoint
{
    /**
     * @param T $subject
     * @param array<string, mixed> $params
     */
    public function __construct(
        final public readonly string $name,
        final public mixed $subject = null {
            set {
                if ($this->readonly ?? false) { // @phpstan-ignore nullCoalesce.property
                    throw new LogicException('Subject can\'t be adjusted in readonly extension points.');
                }
                $this->subject = $value;
            }
        },
        private array $params = [],
        final public readonly bool $readonly = false,
    ) {}

    /** Sets a param. */
    final public function setParam(string $key, mixed $value): void
    {
        if ($this->readonly) {
            throw new LogicException('Params can\'t be adjusted in readonly extension points.');
        }
        $this->params[$key] = $value;
    }

    /** Returns whether the given param exists. */
    final public function hasParam(string $key): bool
    {
        return isset($this->params[$key]);
    }

    /** Returns the param for the given key. */
    final public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Returns all params.
     *
     * @return array<string, mixed>
     */
    final public function getParams(): array
    {
        return $this->params;
    }
}
