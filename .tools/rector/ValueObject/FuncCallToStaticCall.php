<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

final readonly class FuncCallToStaticCall
{
    public function __construct(
        public string $function,
        public string $class,
        public string $method,
    ) {}
}
