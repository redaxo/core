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
final class rex_api_debug extends ApiFunction
{
    protected bool $requiresCsrfProtection = false;

    public function execute(): Result
    {
        if (!Core::isDevMode() || !Core::getUser()?->admin) {
            return new Result(false);
        }

        $debug = rex_debug_clockwork::getHelper();

        Response::sendJson($debug->getMetadata());
        exit;
    }

    public static function getUrlParams(): array
    {
        return [
            self::REQ_CALL_PARAM => 'debug',
            'request' => '',
        ];
    }
}
