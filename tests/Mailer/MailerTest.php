<?php

namespace Redaxo\Core\Tests\Mailer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\Env;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Mailer\Mailer;

/** @internal */
final class MailerTest extends TestCase
{
    #[DataProvider('provideGetErrorMailInterval')]
    public function testGetErrorMailInterval(int $expected, ?string $interval): void
    {
        $orig = Env::get('REX_ERROR_EMAIL_INTERVAL');

        try {
            $_SERVER['REX_ERROR_EMAIL_INTERVAL'] = $interval;
            self::assertSame($expected, Mailer::getErrorMailInterval());
        } finally {
            self::restoreEnv($orig);
        }
    }

    /** @return list<array{int, ?string}> */
    public static function provideGetErrorMailInterval(): array
    {
        return [
            [3600, null],
            [3600, ''],
            [0, '0'],
            [900, '900'],
        ];
    }

    #[DataProvider('provideGetErrorMailIntervalWithInvalidValue')]
    public function testGetErrorMailIntervalWithInvalidValue(string $interval): void
    {
        $orig = Env::get('REX_ERROR_EMAIL_INTERVAL');

        try {
            $_SERVER['REX_ERROR_EMAIL_INTERVAL'] = $interval;
            $this->expectException(InvalidArgumentException::class);
            Mailer::getErrorMailInterval();
        } finally {
            self::restoreEnv($orig);
        }
    }

    /** @return list<array{string}> */
    public static function provideGetErrorMailIntervalWithInvalidValue(): array
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
