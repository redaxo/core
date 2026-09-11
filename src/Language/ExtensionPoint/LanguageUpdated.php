<?php

namespace Redaxo\Core\Language\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

/** @extends ExtensionPoint<null> */
final class LanguageUpdated extends ExtensionPoint
{
    public function __construct(
        public readonly Language $language,
    ) {
        parent::__construct(self::class, null, readonly: true);
    }
}
