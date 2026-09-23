<?php

namespace Redaxo\Core\Tests\Filesystem;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\Url;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final class UrlTest extends TestCase
{
    private ?string $origBaseUrl;
    private ?Request $origRequest;
    private bool $origBackend;

    #[Override]
    protected function setUp(): void
    {
        $this->origBaseUrl = Core::getProject()->baseUrl;
        /** @var Request|null $request */
        $request = Core::getProperty('request');
        $this->origRequest = $request;
        $this->origBackend = (bool) Core::getProperty('redaxo', false);
    }

    #[Override]
    protected function tearDown(): void
    {
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
        $this->unsetBaseUrl();
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
        $this->unsetBaseUrl();
        Core::setProperty('request', null);

        $this->expectException(LogicException::class);
        Url::absoluteBase();
    }

    private function unsetBaseUrl(): void
    {
        Core::getProject()->baseUrl = null;
        if (null !== Core::getProject()->baseUrl) {
            self::markTestSkipped('The env var "REX_BASE_URL" is set.');
        }
    }
}
