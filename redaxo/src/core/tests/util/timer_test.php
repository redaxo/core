<?php

use PHPUnit\Framework\TestCase;

/** @internal */
final class rex_timer_test extends TestCase
{
    /** @var array{enabled: bool, throw_always_exception: bool|int} */
    private array $orgDebug;

    protected function setUp(): void
    {
        // rex_timer internals depend on debug mode..
        $this->orgDebug = rex::getProperty('debug');
        rex::setProperty('debug', true);
    }

    protected function tearDown(): void
    {
        rex::setProperty('debug', $this->orgDebug);
    }

    public function testMeasure(): void
    {
        $callable = static function () {
            /** @var int $i */
            static $i = 1;
            usleep(1);
            return 'result' . ($i++);
        };

        $result = rex_timer::measure('test', $callable);
        self::assertSame('result1', $result);

        self::assertArrayHasKey('test', rex_timer::$serverTimings);
        $timing = rex_timer::$serverTimings['test'];
        self::assertIsFloat($timing['sum']);
        self::assertGreaterThan(0, $timing['sum']);
        self::assertArrayHasKey(0, $timing['timings']);
        self::assertIsFloat($timing['timings'][0]['start']);
        self::assertIsFloat($timing['timings'][0]['end']);
        self::assertGreaterThan($timing['timings'][0]['start'], $timing['timings'][0]['end']);

        $result = rex_timer::measure('test', $callable);

        self::assertSame('result2', $result);
        self::assertGreaterThan($timing['sum'], rex_timer::$serverTimings['test']['sum']);

        $exception = null; // @phpstan-ignore assign.overwritten
        try {
            rex_timer::measure('test2', static function () {
                throw new RuntimeException();
            });
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(RuntimeException::class, $exception);

        self::assertArrayHasKey('test2', rex_timer::$serverTimings);
        $timing = rex_timer::$serverTimings['test2'];
        self::assertIsFloat($timing['sum']);
        self::assertGreaterThan(0, $timing['sum']);
        self::assertArrayHasKey(0, $timing['timings']);
        self::assertIsFloat($timing['timings'][0]['start']);
        self::assertIsFloat($timing['timings'][0]['end']);
        self::assertGreaterThan($timing['timings'][0]['start'], $timing['timings'][0]['end']);
    }
}
