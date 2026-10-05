<?php

namespace Redaxo\Debug\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Util\Type;
use Redaxo\Debug\DebugAddon;

use function dirname;

/**
 * @internal
 */
#[AsApiFunction('debug')]
final class ClockworkMetadata extends ApiFunction
{
    protected bool $requiresCsrfProtection = false;

    public function execute(): Result
    {
        if (!Core::isDevMode() || !Core::getUser()?->admin) {
            return new Result(false);
        }

        Response::sendJson(DebugAddon::instance()->clockworkHelper->getMetadata());
        exit;
    }

    public static function getUrlParams(): array
    {
        return [
            self::REQ_CALL_PARAM => 'debug',
            'request' => '',
        ];
    }

    public static function getUrl(): string
    {
        return Url::backendPage('debug', self::getUrlParams());
    }

    public static function getFullUrl(): string
    {
        $https = isset($_SERVER['HTTPS']) && 'on' == $_SERVER['HTTPS'];
        $host = Type::string($_SERVER['HTTP_HOST'] ?? null);
        $port = $_SERVER['SERVER_PORT'] ?? null;
        $uri = dirname(Type::string($_SERVER['REQUEST_URI'] ?? null)) . '/' . self::getUrl();

        $scheme = $https ? 'https' : 'http';
        $port = (!$https && 80 != $port || $https && 443 != $port) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$port}{$uri}";
    }
}
