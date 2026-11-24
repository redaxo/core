<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

use PHPStan\Type\ObjectType;

final readonly class ReplaceArgumentValue
{
    public function __construct(
        private string $class,
        public string $method,
        public int $position,
        public string|int|float|bool|null $value,
        public string|int|float|bool|null $replacement,
    ) {}

    public function getObjectType(): ObjectType
    {
        return new ObjectType($this->class);
    }
}
