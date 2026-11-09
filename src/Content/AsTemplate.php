<?php

namespace Redaxo\Core\Content;

use Attribute;
use Redaxo\Core\Translation\Translatable;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTemplate
{
    public function __construct(
        public string $key,
        public string|Translatable $name,
    ) {}
}
