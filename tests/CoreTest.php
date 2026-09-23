<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Mode;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final class CoreTest extends TestCase
{
    public function testRexConfig(): void
    {
        $key = 'aTestKey:' . __METHOD__;
        // initial test on empty config
        self::assertFalse(Core::hasConfig($key), 'the key does not exists at first');
        self::assertNull(Core::getConfig($key), 'getting non existing key returns null');
        self::assertEquals(Core::getConfig($key, 'defVal'), 'defVal', 'getting non existing key returns a given default');
        self::assertFalse(Core::removeConfig($key), 'remove non existing key returns false');

        // test after setting a value
        self::assertFalse(Core::setConfig($key, 'aVal'), 'setting non-existant value returns false');
        self::assertEquals(Core::getConfig($key, 'defVal'), 'aVal', 'getting existing key returns its value');
        self::assertTrue(Core::hasConfig($key), 'setted value exists');

        // test after re-setting a value
        self::assertTrue(Core::setConfig($key, 'aOtherVal'), 're-setting a value returns true');
        self::assertEquals(Core::getConfig($key, 'defaOtherVal'), 'aOtherVal', 'getting existing key returns its value');

        // test after cleanup
        self::assertTrue(Core::removeConfig($key), 'remove a existing key returns true');
        self::assertFalse(Core::hasConfig($key), 'the key does not exists after removal');
        self::assertNull(Core::getConfig($key), 'getting non existing key returns null');
        self::assertEquals(Core::getConfig($key, 'defVal'), 'defVal', 'getting non existing key returns a given default');
    }

    public function testRexProperty(): void
    {
        $key = 'aTestKey:' . __METHOD__;
        // initial test on empty config
        self::assertFalse(Core::hasProperty($key), 'the key does not exists at first');
        self::assertNull(Core::getProperty($key), 'getting non existing key returns null');
        self::assertEquals(Core::getProperty($key, 'defVal'), 'defVal', 'getting non existing key returns a given default');
        self::assertFalse(Core::removeProperty($key), 'remove non existing key returns false');

        // test after setting a value
        self::assertFalse(Core::setProperty($key, 'aVal'), 'setting non-existant value returns false');
        self::assertEquals(Core::getProperty($key, 'defVal'), 'aVal', 'getting existing key returns its value');
        self::assertTrue(Core::hasProperty($key), 'setted value exists');

        // test after re-setting a value
        self::assertTrue(Core::setProperty($key, 'aOtherVal'), 're-setting a value returns true');
        self::assertEquals(Core::getProperty($key, 'defaOtherVal'), 'aOtherVal', 'getting existing key returns its value');

        // test after cleanup
        self::assertTrue(Core::removeProperty($key), 'remove a existing key returns true');
        self::assertFalse(Core::hasProperty($key), 'the key does not exists after removal');
        self::assertNull(Core::getProperty($key), 'getting non existing key returns null');
        self::assertEquals(Core::getProperty($key, 'defVal'), 'defVal', 'getting non existing key returns a given default');
    }

    public function testIsBackend(): void
    {
        self::assertTrue(Core::isBackend(), 'test run in the backend');
        // TODO find more appropriate tests
    }

    public function testGetMode(): void
    {
        $origServer = $_SERVER['REX_MODE'] ?? null;
        $origEnv = $_ENV['REX_MODE'] ?? null;

        try {
            $_SERVER['REX_MODE'] = 'dev';
            self::assertSame(Mode::Dev, Core::getMode());
            self::assertTrue(Core::isDevMode());

            $_SERVER['REX_MODE'] = 'live';
            self::assertSame(Mode::Live, Core::getMode());
            self::assertFalse(Core::isDevMode());

            $_SERVER['REX_MODE'] = 'hardened';
            self::assertSame(Mode::Hardened, Core::getMode());
            self::assertFalse(Core::isDevMode());

            unset($_SERVER['REX_MODE'], $_ENV['REX_MODE']);
            self::assertSame(Mode::Live, Core::getMode(), 'the fail-safe fallback is the live mode');

            $_SERVER['REX_MODE'] = 'prod';
            $this->expectException(LogicException::class);
            Core::getMode();
        } finally {
            self::restoreEnv('REX_MODE', $origServer);

            if (null === $origEnv) {
                unset($_ENV['REX_MODE']);
            } else {
                $_ENV['REX_MODE'] = $origEnv;
            }
        }
    }

    public function testGetInstanceId(): void
    {
        $origServer = $_SERVER['REX_INSTANCE_ID'] ?? null;
        $origEnv = $_ENV['REX_INSTANCE_ID'] ?? null;

        try {
            $_SERVER['REX_INSTANCE_ID'] = 'test-instance';
            self::assertSame('test-instance', Core::getInstanceId());

            unset($_SERVER['REX_INSTANCE_ID'], $_ENV['REX_INSTANCE_ID']);
            $this->expectException(LogicException::class);
            Core::getInstanceId();
        } finally {
            self::restoreEnv('REX_INSTANCE_ID', $origServer);

            if (null === $origEnv) {
                unset($_ENV['REX_INSTANCE_ID']);
            } else {
                $_ENV['REX_INSTANCE_ID'] = $origEnv;
            }
        }
    }

    /** @param non-empty-string $name */
    private static function restoreEnv(string $name, ?string $value): void
    {
        if (null === $value) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $value;
        }
    }

    #[DataProvider('provideGetBaseUrlFromEnv')]
    public function testGetBaseUrlFromEnv(string $expected, string $url): void
    {
        $orig = $_SERVER['REX_BASE_URL'] ?? null;

        try {
            $_SERVER['REX_BASE_URL'] = $url;
            self::assertSame($expected, Core::getBaseUrl());
        } finally {
            self::restoreEnv('REX_BASE_URL', $orig);
        }
    }

    /** @return list<array{string, string}> */
    public static function provideGetBaseUrlFromEnv(): array
    {
        return [
            ['https://example.org/', 'https://example.org'],
            ['https://example.org/', 'https://example.org/'],
            ['https://example.org/sub/', 'https://example.org/sub'],
        ];
    }

    public function testGetBaseUrlWithInvalidEnv(): void
    {
        $orig = $_SERVER['REX_BASE_URL'] ?? null;

        try {
            $_SERVER['REX_BASE_URL'] = 'example.org';
            $this->expectException(InvalidArgumentException::class);
            Core::getBaseUrl();
        } finally {
            self::restoreEnv('REX_BASE_URL', $orig);
        }
    }

    #[DataProvider('provideGetBaseUrlFromRequest')]
    public function testGetBaseUrlFromRequest(string $expected, bool $backend, string $scriptName): void
    {
        $origUrl = $_SERVER['REX_BASE_URL'] ?? null;
        $origEnvUrl = $_ENV['REX_BASE_URL'] ?? null;
        /** @var Request|null $origRequest */
        $origRequest = Core::getProperty('request');
        $origBackend = (bool) Core::getProperty('redaxo', false);

        try {
            unset($_SERVER['REX_BASE_URL'], $_ENV['REX_BASE_URL']);
            Core::setProperty('redaxo', $backend);
            Core::setProperty('request', Request::create('https://example.org' . $scriptName, server: [
                'SCRIPT_NAME' => $scriptName,
                'SCRIPT_FILENAME' => '/var/www' . $scriptName,
            ]));

            self::assertSame($expected, Core::getBaseUrl());
        } finally {
            self::restoreEnv('REX_BASE_URL', $origUrl);
            if (null !== $origEnvUrl) {
                $_ENV['REX_BASE_URL'] = $origEnvUrl;
            }
            Core::setProperty('request', $origRequest);
            Core::setProperty('redaxo', $origBackend);
        }
    }

    /** @return list<array{string, bool, string}> */
    public static function provideGetBaseUrlFromRequest(): array
    {
        return [
            ['https://example.org/', false, '/index.php'],
            ['https://example.org/sub/', false, '/sub/index.php'],
            ['https://example.org/', true, '/redaxo/index.php'],
            ['https://example.org/sub/', true, '/sub/redaxo/index.php'],
        ];
    }

    public function testGetBaseUrlWithoutEnvAndRequest(): void
    {
        $origUrl = $_SERVER['REX_BASE_URL'] ?? null;
        $origEnvUrl = $_ENV['REX_BASE_URL'] ?? null;
        /** @var Request|null $origRequest */
        $origRequest = Core::getProperty('request');

        try {
            unset($_SERVER['REX_BASE_URL'], $_ENV['REX_BASE_URL']);
            Core::setProperty('request', null);

            $this->expectException(LogicException::class);
            Core::getBaseUrl();
        } finally {
            self::restoreEnv('REX_BASE_URL', $origUrl);
            if (null !== $origEnvUrl) {
                $_ENV['REX_BASE_URL'] = $origEnvUrl;
            }
            Core::setProperty('request', $origRequest);
        }
    }

    public function testGetInstanceName(): void
    {
        $origServer = $_SERVER['REX_INSTANCE_NAME'] ?? null;
        $origEnv = $_ENV['REX_INSTANCE_NAME'] ?? null;

        try {
            $_SERVER['REX_INSTANCE_NAME'] = 'My Site';
            self::assertSame('My Site', Core::getInstanceName());

            unset($_SERVER['REX_INSTANCE_NAME'], $_ENV['REX_INSTANCE_NAME']);
            self::assertSame('REDAXO', Core::getInstanceName());
        } finally {
            self::restoreEnv('REX_INSTANCE_NAME', $origServer);
            if (null !== $origEnv) {
                $_ENV['REX_INSTANCE_NAME'] = $origEnv;
            }
        }
    }

    public function testGetVersion(): void
    {
        self::assertTrue('' != Core::getVersion(), 'a version string is returned');
        $vers = Core::getVersion();
        $versParts = explode('.', $vers);
        self::assertTrue(6 == $versParts[0], 'the major version is 6');
    }

    public function testGetHttpClient(): void
    {
        self::assertSame(Core::getHttpClient(), Core::getHttpClient(), 'the same shared client instance is returned');
    }
}
