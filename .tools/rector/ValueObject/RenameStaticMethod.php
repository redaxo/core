<?php

declare(strict_types=1);

namespace Redaxo\Rector\ValueObject;

use PHPStan\Type\ObjectType;

final readonly class RenameStaticMethod
{
    public function __construct(
        private string $oldClass,
        public string $oldMethod,
        public string $newClass,
        public string $newMethod,
    ) {}

    public function getOldObjectType(): ObjectType
    {
        return new ObjectType($this->oldClass);
    }
}
