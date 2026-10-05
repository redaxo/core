<?php

namespace Redaxo\Debug;

use Redaxo\Core\ApiFunction\ApiFunction;

/**
 * @internal
 */
abstract class DebugApiFunction extends ApiFunction
{
    public static function handleCall(): void
    {
        $apiFunc = self::factory();

        if (null !== $apiFunc) {
            DebugAddon::instance()->clockwork->log('debug', 'called api function "' . $apiFunc::class . '"');
        }

        parent::handleCall();
    }
}
