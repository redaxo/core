<?php

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Response;

/**
 * @internal
 */
#[AsApiFunction('debug')]
class rex_api_debug extends ApiFunction
{
    public function execute()
    {
        if (!Core::isDebugMode() || !Core::getUser()?->isAdmin()) {
            return new Result(false);
        }

        $debug = rex_debug_clockwork::getHelper();

        Response::sendJson($debug->getMetadata());
        exit;
    }

    protected function requiresCsrfProtection(): bool
    {
        return false;
    }

    public static function getUrlParams()
    {
        return [
            self::REQ_CALL_PARAM => 'debug',
            'request' => '',
        ];
    }
}
