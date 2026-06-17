<?php

namespace Redaxo\Core\MetaInfo;

use Attribute;

/**
 * Marks a {@see MetaSchema} subclass and binds it to an entity.
 *
 * Exactly one schema per entity is supported.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMetaSchema
{
    public function __construct(
        public MetaEntity $entity,
    ) {}
}
