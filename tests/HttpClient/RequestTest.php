<?php

namespace Redaxo\Core\Tests\HttpClient;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\HttpClient\Exception\HttpClientException;
use Redaxo\Core\HttpClient\ProxyRequest;
use Redaxo\Core\HttpClient\Request;
use Redaxo\Core\HttpClient\Response;
use ReflectionClass;
use ReflectionMethod;

/** @internal */
final class RequestTest extends TestCase
{
    private ?string $proxy = null;

    #[Override]
    protected function setUp(): void
    {
        $this->proxy = Core::getProperty('http_client_proxy');
        Core::setProperty('http_client_proxy', null);
    }

    #[Override]
    protected function tearDown(): void
    {
        Core::setProperty('http_client_proxy', $this->proxy);
    }

    public function testFactory(): Request
    {
        $socket = Request::factory('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(Request::class, $socket::class);
        return $socket;
    }

    public function testFactoryProxy(): void
    {
        Core::setProperty('http_client_proxy', 'proxy.example.com:8888');
        $socket = Request::factory('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(ProxyRequest::class, $socket::class);
    }

    public function testFactoryUrl(): void
    {
        $socket = Request::factoryUrl('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(Request::class, $socket::class);
    }

    public function testFactoryUrlProxy(): void
    {
        Core::setProperty('http_client_proxy', 'proxy.example.com:8888');
        $socket = Request::factoryUrl('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(ProxyRequest::class, $socket::class);
    }

    #[Depends('testFactory')]
    public function testWriteRequest(Request $socket): void
    {
        $class = new ReflectionClass(Request::class);
        $property = $class->getProperty('stream');
        $method = $class->getMethod('writeRequest');

        $stream = fopen('php://temp', 'r+');
        $property->setValue($socket, $stream);

        try {
            $method->invoke($socket, 'GET', '/a/path', ['Host' => 'www.example.com', 'Connection' => 'Close'], "body1\r\nbody2");
        } catch (HttpClientException) {
            // ignore "Missing status code in response header" error in Response class
        }

        $eol = "\r\n";
        $expected = 'GET /a/path HTTP/1.1' . $eol
            . 'Host: www.example.com' . $eol
            . 'Connection: Close' . $eol
            . 'Content-Length: 12' . $eol
            . $eol
            . 'body1' . $eol
            . 'body2';
        fseek($stream, 0);
        self::assertEquals($expected, fread($stream, 1024));
        fclose($stream);
    }

    /** @return list<array{string, string, int, bool, string}> */
    public static function parseUrlProvider(): array
    {
        return [
            ['example.com',                             'example.com', 443,  true, '/'],
            ['example.com:81',                          'example.com', 81,  false, '/'],
            ['example.com/a/path/?key=value',           'example.com', 443,  true, '/a/path/?key=value'],
            ['example.com:81/a/path/?key=value',        'example.com', 81,  false, '/a/path/?key=value'],
            ['http://example.com',                      'example.com', 80,  false, '/'],
            ['https://example.com',                     'example.com', 443, true,  '/'],
            ['http://example.com:81',                   'example.com', 81,  false, '/'],
            ['https://example.com:444',                 'example.com', 444, true,  '/'],
            ['http://example.com/a/path/?key=value',    'example.com', 80,  false, '/a/path/?key=value'],
            ['http://example.com:81/a/path/?key=value', 'example.com', 81,  false, '/a/path/?key=value'],
        ];
    }

    #[DataProvider('parseUrlProvider')]
    public function testParseUrl(string $url, string $expectedHost, int $expectedPort, bool $expectedSsl, string $expectedPath): void
    {
        $method = new ReflectionMethod(Request::class, 'parseUrl');
        /** @psalm-suppress MixedAssignment */
        $result = $method->invoke(null, $url);
        $expected = [
            'host' => $expectedHost,
            'port' => $expectedPort,
            'ssl' => $expectedSsl,
            'path' => $expectedPath,
        ];
        self::assertEquals($expected, $result);
    }

    /** @return list<array{string}> */
    public static function parseUrlExceptionProvider(): array
    {
        return [
            [''],
            ['http://'],
            ['abc://example.com'],
        ];
    }

    #[DataProvider('parseUrlExceptionProvider')]
    public function testParseUrlException(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $method = new ReflectionMethod(Request::class, 'parseUrl');
        $method->invoke(null, $url);
    }
}
