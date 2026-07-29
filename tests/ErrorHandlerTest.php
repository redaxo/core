<?php

namespace Redaxo\Core\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\ErrorHandler;
use Redaxo\Core\Exception\LogicException;

use const E_ALL;
use const E_DEPRECATED;
use const E_NOTICE;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;

/** @internal */
final class ErrorHandlerTest extends TestCase
{
    private ?string $origMode = null;
    private ?string $origThrow = null;

    #[Override]
    protected function setUp(): void
    {
        $this->origMode = $_SERVER['REX_MODE'] ?? null;
        $this->origThrow = $_SERVER['REX_ERROR_THROW'] ?? null;

        unset($_ENV['REX_ERROR_THROW']);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['REX_MODE' => $this->origMode, 'REX_ERROR_THROW' => $this->origThrow] as $name => $value) {
            if (null === $value) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $value;
            }
        }
    }

    #[DataProvider('provideThrowErrorLevels')]
    public function testGetThrowErrorLevels(int $expected, string $mode, ?string $levels): void
    {
        $_SERVER['REX_MODE'] = $mode;
        if (null === $levels) {
            unset($_SERVER['REX_ERROR_THROW']);
        } else {
            $_SERVER['REX_ERROR_THROW'] = $levels;
        }

        self::assertSame($expected, ErrorHandler::getThrowErrorLevels());
    }

    /** @return list<array{int, string, string|null}> */
    public static function provideThrowErrorLevels(): array
    {
        return [
            // mode based defaults
            [E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE, 'dev', null],
            [0, 'live', null],
            [0, 'hardened', null],

            // explicit values via env var
            [0, 'dev', 'none'],
            [E_ALL, 'live', 'all'],
            [E_WARNING, 'live', 'E_WARNING'],
            [E_WARNING | E_NOTICE, 'live', 'E_WARNING,E_NOTICE'],
            [E_WARNING | E_DEPRECATED, 'live', ' E_WARNING , E_DEPRECATED '],
        ];
    }

    #[DataProvider('provideInvalidThrowErrorLevels')]
    public function testGetThrowErrorLevelsInvalid(string $levels): void
    {
        $_SERVER['REX_ERROR_THROW'] = $levels;

        $this->expectException(LogicException::class);
        ErrorHandler::getThrowErrorLevels();
    }

    /** @return list<array{string}> */
    public static function provideInvalidThrowErrorLevels(): array
    {
        return [
            ['E_WARNING,E_FOO'],
            ['warning'],
            ['e_warning'],
        ];
    }
}
