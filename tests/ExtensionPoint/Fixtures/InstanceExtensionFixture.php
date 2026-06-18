<?php

namespace Redaxo\Core\Tests\ExtensionPoint\Fixtures;

use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Fixture for non-static {@see AsExtension} methods backed by a registered instance.
 * Carries a constructor argument on purpose, to mirror objects that cannot be created without parameters.
 *
 * @internal
 */
class InstanceExtensionFixture
{
    public function __construct(
        private readonly string $marker,
    ) {}

    /** @param ExtensionPoint<string> $ep */
    #[AsExtension('TEST_INSTANCE_EXTENSION_EP')]
    public function append(ExtensionPoint $ep): string
    {
        return $ep->subject . $this->marker;
    }
}
