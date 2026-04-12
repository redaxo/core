<?php

namespace Redaxo\Core\Content;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsModule
{
    public function __construct(
        public string $key,
        public string $name,
    ) {}
}
