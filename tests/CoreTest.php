<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Mode;

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

    public function testIsSetup(): void
    {
        self::assertFalse(Core::isSetup(), 'test run not within the setup');
        // TODO find more appropriate tests
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

    public function testIsSafeMode(): void
    {
        $origMode = $_SERVER['REX_MODE'] ?? null;
        $origSafeMode = $_SERVER['REX_SAFE_MODE'] ?? null;

        try {
            $_SERVER['REX_MODE'] = 'dev';
            $_SERVER['REX_SAFE_MODE'] = '1';
            self::assertTrue(Core::isSafeModeForced());
            self::assertTrue(Core::isSafeMode());

            // the env var based safe mode works even in the hardened mode (only the session based one is blocked)
            $_SERVER['REX_MODE'] = 'hardened';
            self::assertTrue(Core::isSafeMode());

            $_SERVER['REX_SAFE_MODE'] = '0';
            self::assertFalse(Core::isSafeModeForced());
            self::assertFalse(Core::isSafeMode());
        } finally {
            self::restoreEnv('REX_MODE', $origMode);
            self::restoreEnv('REX_SAFE_MODE', $origSafeMode);
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

    public function testGetTablePrefix(): void
    {
        self::assertEquals(Core::getTablePrefix(), 'rex_', 'table prefix defauts to rex_');
    }

    public function testGetTable(): void
    {
        self::assertEquals(Core::getTable('mytable'), 'rex_mytable', 'tablename gets properly prefixed');
    }

    public function testGetTempPrefix(): void
    {
        self::assertEquals(Core::getTempPrefix(), 'tmp_', 'temp prefix defaults to tmp_');
    }

    public function testGetServer(): void
    {
        $origServer = Core::getProperty('server');

        try {
            Core::setProperty('server', 'http://www.redaxo.org');
            self::assertEquals('http://www.redaxo.org/', Core::getServer());
            self::assertEquals('https://www.redaxo.org/', Core::getServer('https'));
            self::assertEquals('www.redaxo.org/', Core::getServer(''));
        } finally {
            Core::setProperty('server', $origServer);
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
