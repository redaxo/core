<?php

namespace Redaxo\Core\ExtensionPoint;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsExtension
{
    /** @param Extension::EARLY|Extension::NORMAL|Extension::LATE $level */
    public function __construct(
        public string $extensionPoint,
        public int $level = Extension::NORMAL,
    ) {}
}
