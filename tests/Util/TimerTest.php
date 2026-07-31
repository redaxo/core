<?php

namespace Redaxo\Core\Tests\Util;

use Override;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Util\Timer;
use Throwable;

/** @internal */
final class TimerTest extends TestCase
{
    private ?string $orgMode;

    #[Override]
    protected function setUp(): void
    {
        // Timer internals depend on debug mode..
        $this->orgMode = $_SERVER['REX_MODE'] ?? null;
        $_SERVER['REX_MODE'] = 'dev';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null === $this->orgMode) {
            unset($_SERVER['REX_MODE']);
        } else {
            $_SERVER['REX_MODE'] = $this->orgMode;
        }
    }

    public function testMeasure(): void
    {
        $callable = static function () {
            /** @var int $i */
            static $i = 1;
            usleep(1);
            return 'result' . ($i++);
        };

        $result = Timer::measure('test', $callable);
        self::assertSame('result1', $result);

        self::assertArrayHasKey('test', Timer::$serverTimings);
        $timing = Timer::$serverTimings['test'];
        self::assertIsFloat($timing['sum']);
        self::assertGreaterThan(0, $timing['sum']);
        self::assertArrayHasKey(0, $timing['timings']);
        self::assertIsFloat($timing['timings'][0]['start']);
        self::assertIsFloat($timing['timings'][0]['end']);
        self::assertGreaterThan($timing['timings'][0]['start'], $timing['timings'][0]['end']);

        $result = Timer::measure('test', $callable);

        self::assertSame('result2', $result);
        self::assertGreaterThan($timing['sum'], Timer::$serverTimings['test']['sum']);

        $exception = null;
        try {
            Timer::measure('test2', static function (): never {
                throw new RuntimeException('test');
            });
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(RuntimeException::class, $exception);

        self::assertArrayHasKey('test2', Timer::$serverTimings);
        $timing = Timer::$serverTimings['test2'];
        self::assertIsFloat($timing['sum']);
        self::assertGreaterThan(0, $timing['sum']);
        self::assertArrayHasKey(0, $timing['timings']);
        self::assertIsFloat($timing['timings'][0]['start']);
        self::assertIsFloat($timing['timings'][0]['end']);
        self::assertGreaterThan($timing['timings'][0]['start'], $timing['timings'][0]['end']);
    }
}
