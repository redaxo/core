<?php

namespace Redaxo\Core\Addon\ExtensionPoint;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * @extends ExtensionPoint<Addon>
 */
final class AddonCacheDeleted extends ExtensionPoint
{
    public const string NAME = 'ADDON_CACHE_DELETED';

    public function __construct(Addon $package)
    {
        parent::__construct(self::NAME, $package, readonly: true);
    }
}
