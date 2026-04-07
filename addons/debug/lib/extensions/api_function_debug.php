<?php

use Redaxo\Core\ApiFunction\ApiFunction;

/**
 * @internal
 */
abstract class rex_api_function_debug extends ApiFunction
{
    public static function handleCall()
    {
        $apiFunc = self::factory();

        if (null !== $apiFunc) {
            rex_debug_clockwork::getInstance()->log('debug', 'called api function "' . $apiFunc::class . '"');
        }

        parent::handleCall();
    }
}
