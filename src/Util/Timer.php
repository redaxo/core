<?php

namespace Redaxo\Core\Util;

use Redaxo\Core\Core;

/**
 * Class to stop the script time.
 */
final class Timer
{
    public const int SEC = 1;
    public const int MILLISEC = 1_000;
    public const int MICROSEC = 1_000_000;

    /**
     * @internal
     * @var array<string, array{sum: float, timings: list<array{start: float, end: float}>}>
     */
    public static array $serverTimings = [];

    private float $start;
    private ?float $duration = null;

    /** @param float|null $start Start time */
    public function __construct(?float $start = null)
    {
        if ($start) {
            $this->start = $start;
        } else {
            $this->reset();
        }
    }

    /**
     * Measures the runtime of the given callable.
     *
     * On sufficient user permissions - or in debug mode - this timings will be sent over the wire to the browser via server timing api http headers.
     *
     * @template T
     *
     * @param callable():T $callable
     *
     * @return T result of callable
     */
    public static function measure(string $label, callable $callable): mixed
    {
        if (!Core::isDebugMode()) {
            return $callable();
        }

        $timer = new self();

        try {
            return $callable();
        } finally {
            $timer->stop();

            self::measured($label, $timer);
        }
    }

    /**
     * Saves the measurement of the given timer.
     *
     * This method should be used only if the measured code can not be wrapped inside a callable, otherwise use `measure()`.
     */
    public static function measured(string $label, self $timer): void
    {
        $duration = self::$serverTimings[$label]['sum'] ?? 0;
        $duration += $timer->getDelta(self::MILLISEC);

        self::$serverTimings[$label]['sum'] = $duration;
        self::$serverTimings[$label]['timings'][] = [
            'start' => $timer->start,
            'end' => microtime(true),
        ];
    }

    /** Resets the timer. */
    public function reset(): void
    {
        $this->start = microtime(true);
    }

    /** Stops the timer. */
    public function stop(): void
    {
        $this->duration = microtime(true) - $this->start;
    }

    /**
     * Returns the time difference.
     *
     * @param int $precision Factor which will be multiplied, for conversion into different units (e.g. 1000 for milli,...)
     *
     * @return float Time difference
     */
    public function getDelta(int $precision = self::MILLISEC): float
    {
        $duration = $this->duration ?? microtime(true) - $this->start;

        return $duration * $precision;
    }

    /**
     * Returns the formatted time difference.
     *
     * @param int $precision Factor which will be multiplied, for conversion into different units (e.g. 1000 for milli,...)
     * @param int $decimals Number of decimals points
     *
     * @return string Formatted time difference
     */
    public function getFormattedDelta(int $precision = self::MILLISEC, int $decimals = 3): string
    {
        $time = $this->getDelta($precision);
        return Formatter::number($time, [$decimals]);
    }
}
