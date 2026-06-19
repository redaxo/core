<?php

namespace Redaxo\Core\MediaManager\Attribute;

use Attribute;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaManager\MediaType;

/**
 * Registers a {@see MediaType} under the given name.
 *
 * The name is what is referenced in URLs (`rex_media_type=...`) and in {@see MediaManager::getUrl()}.
 *
 * The attribute is repeatable: a single class may register several named types and receive
 * per-name configuration through additional constructor arguments — useful for families of
 * similar types:
 *
 *     #[AsMediaType('teaser_s', maxSize: 200)]
 *     #[AsMediaType('teaser_l', maxSize: 800)]
 *     final class Teaser implements MediaType
 *     {
 *         public function __construct(private int $maxSize) {}
 *         // ...
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsMediaType
{
    /** @var array<string, mixed> Named arguments forwarded to the type's constructor */
    public array $arguments;

    public function __construct(
        public string $name,
        mixed ...$arguments,
    ) {
        /** @var array<string, mixed> $arguments */
        $this->arguments = $arguments;
    }
}
