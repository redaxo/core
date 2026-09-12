<?php

namespace Redaxo\Core\Language\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

/**
 * Dispatched once the language and all its articles and slices are gone.
 *
 * @extends ExtensionPoint<null>
 */
final class LanguageDeleted extends ExtensionPoint
{
    public function __construct(
        public readonly Language $language,
    ) {
        parent::__construct(self::class, null, readonly: true);
    }
}
