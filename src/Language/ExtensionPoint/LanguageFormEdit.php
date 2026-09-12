<?php

namespace Redaxo\Core\Language\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

/**
 * Extra table rows below the language edit row.
 *
 * @extends ExtensionPoint<string>
 */
final class LanguageFormEdit extends ExtensionPoint
{
    public function __construct(
        public readonly Language $language,
    ) {
        parent::__construct(self::class, '');
    }
}
