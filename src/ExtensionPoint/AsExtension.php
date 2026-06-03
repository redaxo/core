<?php

namespace Redaxo\Core\ExtensionPoint;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsExtension
{
    /**
     * @param string|null $extensionPoint Name of the extension point. May be omitted if the extension method's first
     *     parameter is typed as a concrete {@see ExtensionPoint} subclass — the name is then derived from that class.
     */
    public function __construct(
        public ?string $extensionPoint = null,
        public ExtensionLevel $level = ExtensionLevel::Normal,
    ) {}
}
