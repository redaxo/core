<?php

namespace Redaxo\Core\Tests;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Core;

use const E_NOTICE;
use const E_WARNING;

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

    public function testDebugFlags(): void
    {
        $orgDebug = Core::getProperty('debug');
        try {
            $debug = [
                'enabled' => false,
                'throw_always_exception' => false,
            ];
            Core::setProperty('debug', $debug);

            self::assertFalse(Core::isDebugMode());
            self::assertSame($debug, Core::getDebugFlags());

            Core::setProperty('debug', true);

            self::assertTrue(Core::isDebugMode());
            self::assertArrayHasKey('throw_always_exception', Core::getDebugFlags());
            self::assertFalse(Core::getDebugFlags()['throw_always_exception']); // @phpstan-ignore-line

            Core::setProperty('debug', ['enabled' => false]);

            self::assertFalse(Core::isDebugMode());
            self::assertArrayHasKey('throw_always_exception', Core::getDebugFlags());
            self::assertFalse(Core::getDebugFlags()['throw_always_exception']);

            $debug = [
                'enabled' => true,
                'throw_always_exception' => true,
            ];
            Core::setProperty('debug', $debug);
            self::assertSame($debug, Core::getDebugFlags());

            $debug = [
                'enabled' => true,
                'throw_always_exception' => E_WARNING | E_NOTICE,
            ];
            Core::setProperty('debug', $debug);
            self::assertSame($debug, Core::getDebugFlags());

            Core::setProperty('debug', [
                'enabled' => true,
                'throw_always_exception' => ['E_WARNING', 'E_NOTICE'],
            ]);
            self::assertSame($debug, Core::getDebugFlags());
        } finally {
            Core::setProperty('debug', $orgDebug);
        }
    }

    public function testLiveModeFlag(): void
    {
        $origLiveMode = Core::getProperty('live_mode');
        $origSafeMode = Core::getProperty('safe_mode');
        $origDebug = Core::getProperty('debug');

        try {
            Core::setProperty('live_mode', false);
            Core::setProperty('safe_mode', true);
            Core::setProperty('debug', true);
            self::assertFalse(Core::isLiveMode());
            self::assertTrue(Core::isSafeMode());
            self::assertTrue(Core::isDebugMode());

            Core::setProperty('live_mode', true);
            self::assertTrue(Core::isLiveMode());
            self::assertFalse(Core::isSafeMode());
            self::assertFalse(Core::isDebugMode());
        } finally {
            Core::setProperty('live_mode', $origLiveMode);
            Core::setProperty('safe_mode', $origSafeMode);
            Core::setProperty('debug', $origDebug);
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
