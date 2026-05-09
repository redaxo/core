<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

use PHPStan\Type\ObjectType;

final readonly class SetterCallToConstructorArgument
{
    public function __construct(
        private string $class,
        public string $method,
        public string $argumentName,
    ) {}

    public function getObjectType(): ObjectType
    {
        return new ObjectType($this->class);
    }
}
