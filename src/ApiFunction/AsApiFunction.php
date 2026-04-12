<?php

namespace Redaxo\Core\ApiFunction;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsApiFunction
{
    public function __construct(
        public string $name,
    ) {}
}
