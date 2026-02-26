<?php

/**
 * Class to stop the script time.
 *
 * @author gharlan
 *
 * @package redaxo\core
 */
class rex_timer
{
    public const SEC = 1;
    public const MILLISEC = 1_000;
    public const MICROSEC = 1_000_000;

    /**
     * @internal
     *
     * @var array<string, array{sum: mixed, timings: list<array{start: float, end: float}>}>
     */
    public static $serverTimings = [];

    /** @var float */
    private $start;

    /** @var float|null */
    private $duration;

    /** @param float $start Start time */
    public function __construct($start = null)
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
     * @param string $label
     * @param callable():T $callable
     *
     * @return T result of callable
     */
    public static function measure($label, callable $callable)
    {
        if (!rex::isDebugMode()) {
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

    /**
     * Resets the timer.
     * @return void
     */
    public function reset()
    {
        $this->start = microtime(true);
    }

    /**
     * Stops the timer.
     * @return void
     */
    public function stop()
    {
        $this->duration = microtime(true) - $this->start;
    }

    /**
     * Returns the time difference.
     *
     * @param int $precision Factor which will be multiplied, for convertion into different units (e.g. 1000 for milli,...)
     *
     * @return float Time difference
     */
    public function getDelta($precision = self::MILLISEC)
    {
        $duration = $this->duration ?? microtime(true) - $this->start;

        return $duration * $precision;
    }

    /**
     * Returns the formatted time difference.
     *
     * @param int $precision Factor which will be multiplied, for convertion into different units (e.g. 1000 for milli,...)
     * @param int $decimals Number of decimals points
     *
     * @return string Formatted time difference
     */
    public function getFormattedDelta($precision = self::MILLISEC, $decimals = 3)
    {
        $time = $this->getDelta($precision);
        return rex_formatter::number($time, [$decimals]);
    }
}
