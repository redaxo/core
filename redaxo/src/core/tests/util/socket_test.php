<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_socket_test extends TestCase
{
    private ?string $proxy = null;
    /** @var string[] */
    private array $proxyIgnore = [];

    protected function setUp(): void
    {
        $this->proxy = rex::getProperty('socket_proxy');
        $this->proxyIgnore = rex::getProperty('socket_proxy_ignore', []);
        rex::setProperty('socket_proxy', null);
        rex::setProperty('socket_proxy_ignore', []);
    }

    protected function tearDown(): void
    {
        rex::setProperty('socket_proxy', $this->proxy);
        rex::setProperty('socket_proxy_ignore', $this->proxyIgnore);
    }

    public function testFactory(): rex_socket
    {
        $socket = rex_socket::factory('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(rex_socket::class, $socket::class);
        return $socket;
    }

    public function testFactoryProxy(): void
    {
        rex::setProperty('socket_proxy', 'proxy.example.com:8888');
        $socket = rex_socket::factory('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(rex_socket_proxy::class, $socket::class);
    }

    /** @param class-string<rex_socket> $expectedClass */
    #[DataProvider('provideFactoryProxyIgnore')]
    public function testFactoryProxyIgnore(string $url, string $expectedClass): void
    {
        rex::setProperty('socket_proxy', 'proxy.example.com:8888');
        rex::setProperty('socket_proxy_ignore', ['www.example.com', 'LOCALHOST']);
        $socket = rex_socket::factoryUrl($url);
        $socket->setOptions([]);
        self::assertEquals($expectedClass, $socket::class);
    }

    /** @return list<array{string, class-string<rex_socket>}> */
    public static function provideFactoryProxyIgnore(): array
    {
        return [
            ['www.example.com', rex_socket::class],
            ['https://WWW.Example.com/path', rex_socket::class],
            ['http://localhost:8080', rex_socket::class],
            ['example.com', rex_socket_proxy::class],
            ['sub.www.example.com', rex_socket_proxy::class],
        ];
    }

    public function testFactoryUrl(): void
    {
        $socket = rex_socket::factoryUrl('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(rex_socket::class, $socket::class);
    }

    public function testFactoryUrlProxy(): void
    {
        rex::setProperty('socket_proxy', 'proxy.example.com:8888');
        $socket = rex_socket::factoryUrl('www.example.com');
        $socket->setOptions([]);
        self::assertEquals(rex_socket_proxy::class, $socket::class);
    }

    #[Depends('testFactory')]
    public function testWriteRequest(rex_socket $socket): void
    {
        $class = new ReflectionClass(rex_socket::class);
        $property = $class->getProperty('stream');
        $method = $class->getMethod('writeRequest');

        $stream = fopen('php://temp', 'r+');
        $property->setValue($socket, $stream);
        $response = $method->invoke($socket, 'GET', '/a/path', ['Host' => 'www.example.com', 'Connection' => 'Close'], "body1\r\nbody2");

        self::assertInstanceOf(rex_socket_response::class, $response);

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
            ['example.com',                             'example.com', 80,  false, '/'],
            ['example.com:81',                          'example.com', 81,  false, '/'],
            ['example.com/a/path/?key=value',           'example.com', 80,  false, '/a/path/?key=value'],
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
        $method = new ReflectionMethod(rex_socket::class, 'parseUrl');
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
        $this->expectException(rex_socket_exception::class);

        $method = new ReflectionMethod(rex_socket::class, 'parseUrl');
        $method->invoke(null, $url);
    }
}
