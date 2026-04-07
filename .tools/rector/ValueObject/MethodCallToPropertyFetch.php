<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

use PHPStan\Type\ObjectType;

final readonly class MethodCallToPropertyFetch
{
    public function __construct(
        private string $class,
        public string $method,
        public string $property,
    ) {}

    public function getObjectType(): ObjectType
    {
        return new ObjectType($this->class);
    }
}
