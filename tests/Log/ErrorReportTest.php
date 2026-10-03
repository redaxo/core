<?php

namespace Redaxo\Core\Tests\Log;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Log\ErrorReport;

/** @internal */
final class ErrorReportTest extends TestCase
{
    #[DataProvider('provideGetInterval')]
    public function testGetInterval(int $expected, ?string $interval): void
    {
        $orig = Env::get('REX_ERROR_EMAIL_INTERVAL');

        try {
            $_SERVER['REX_ERROR_EMAIL_INTERVAL'] = $interval;
            self::assertSame($expected, ErrorReport::getInterval());
        } finally {
            self::restoreEnv($orig);
        }
    }

    /** @return list<array{int, ?string}> */
    public static function provideGetInterval(): array
    {
        return [
            [3600, null],
            [3600, ''],
            [0, '0'],
            [900, '900'],
        ];
    }

    #[DataProvider('provideGetIntervalWithInvalidValue')]
    public function testGetIntervalWithInvalidValue(string $interval): void
    {
        $orig = Env::get('REX_ERROR_EMAIL_INTERVAL');

        try {
            $_SERVER['REX_ERROR_EMAIL_INTERVAL'] = $interval;
            $this->expectException(InvalidArgumentException::class);
            ErrorReport::getInterval();
        } finally {
            self::restoreEnv($orig);
        }
    }

    /** @return list<array{string}> */
    public static function provideGetIntervalWithInvalidValue(): array
    {
        return [
            ['-1'],
            ['15min'],
        ];
    }

    private static function restoreEnv(?string $value): void
    {
        if (null === $value) {
            unset($_SERVER['REX_ERROR_EMAIL_INTERVAL']);
        } else {
            $_SERVER['REX_ERROR_EMAIL_INTERVAL'] = $value;
        }
    }
}
