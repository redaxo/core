<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

final readonly class NewToStaticCall
{
    public function __construct(
        public string $class,
        public string $staticCallClass,
        public string $staticCallMethod,
    ) {}
}
