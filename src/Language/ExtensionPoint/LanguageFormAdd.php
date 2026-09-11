<?php

namespace Redaxo\Core\Language\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Extra table rows below the language add row.
 *
 * @extends ExtensionPoint<string>
 */
final class LanguageFormAdd extends ExtensionPoint
{
    public function __construct()
    {
        parent::__construct(self::class, '');
    }
}
