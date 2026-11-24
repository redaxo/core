<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

use PHPStan\Type\ObjectType;

final readonly class MethodCallToStaticCall
{
    public function __construct(
        private string $class,
        public string $method,
        public string $staticCallClass,
        public string $staticCallMethod,
    ) {}

    public function getObjectType(): ObjectType
    {
        return new ObjectType($this->class);
    }
}
