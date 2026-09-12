<?php

namespace Redaxo\Core\Language\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;

/**
 * Buttons in the action column of the language add/edit row.
 *
 * @extends ExtensionPoint<string>
 */
final class LanguageFormButtons extends ExtensionPoint
{
    /** @param Language|null $language the edited language, `null` while adding */
    public function __construct(
        public readonly ?Language $language = null,
    ) {
        parent::__construct(self::class, '');
    }
}
