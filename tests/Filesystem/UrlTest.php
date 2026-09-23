<?php

namespace Redaxo\Core\Tests\Filesystem;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Util\Type;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final class UrlTest extends TestCase
{
    private ?string $origServerEnv;
    private ?string $origEnv;
    private ?string $origBaseUrl;
    private ?Request $origRequest;
    private bool $origBackend;

    #[Override]
    protected function setUp(): void
    {
        // the tests must not depend on a base url the instance running them configures
        $this->origServerEnv = Type::nullOrString($_SERVER['REX_BASE_URL'] ?? null);
        $this->origEnv = Type::nullOrString($_ENV['REX_BASE_URL'] ?? null);
        unset($_SERVER['REX_BASE_URL'], $_ENV['REX_BASE_URL']);

        $this->origBaseUrl = Core::getProject()->baseUrl;
        /** @var Request|null $request */
        $request = Core::getProperty('request');
        $this->origRequest = $request;
        $this->origBackend = (bool) Core::getProperty('redaxo', false);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->origServerEnv) {
            $_SERVER['REX_BASE_URL'] = $this->origServerEnv;
        }
        if (null !== $this->origEnv) {
            $_ENV['REX_BASE_URL'] = $this->origEnv;
        }

        Core::getProject()->baseUrl = $this->origBaseUrl;
        Core::setProperty('request', $this->origRequest);
        Core::setProperty('redaxo', $this->origBackend);
    }

    #[DataProvider('provideAbsoluteBaseWithBaseUrl')]
    public function testAbsoluteBaseWithBaseUrl(string $expected, string $baseUrl, string $file): void
    {
        Core::getProject()->baseUrl = $baseUrl;

        self::assertSame($expected, Url::absoluteBase($file));
    }

    /** @return list<array{string, string, string}> */
    public static function provideAbsoluteBaseWithBaseUrl(): array
    {
        return [
            ['https://example.org/', 'https://example.org', ''],
            ['https://example.org/', 'https://example.org/', ''],
            ['https://example.org/sub/', 'https://example.org/sub', ''],
            ['https://example.org/sub/media/foo.jpg', 'https://example.org/sub/', 'media/foo.jpg'],
        ];
    }

    #[DataProvider('provideAbsoluteBaseFromRequest')]
    public function testAbsoluteBaseFromRequest(string $expected, bool $backend, string $scriptName): void
    {
        Core::getProject()->baseUrl = null;
        Core::setProperty('redaxo', $backend);
        Core::setProperty('request', Request::create('https://example.org' . $scriptName, server: [
            'SCRIPT_NAME' => $scriptName,
            'SCRIPT_FILENAME' => '/var/www' . $scriptName,
        ]));

        self::assertSame($expected, Url::absoluteBase());
    }

    /** @return list<array{string, bool, string}> */
    public static function provideAbsoluteBaseFromRequest(): array
    {
        return [
            ['https://example.org/', false, '/index.php'],
            ['https://example.org/sub/', false, '/sub/index.php'],
            ['https://example.org/', true, '/redaxo/index.php'],
            ['https://example.org/sub/', true, '/sub/redaxo/index.php'],
        ];
    }

    public function testAbsoluteBaseWithoutBaseUrlAndRequest(): void
    {
        Core::getProject()->baseUrl = null;
        Core::setProperty('request', null);

        $this->expectException(LogicException::class);
        Url::absoluteBase();
    }
}
