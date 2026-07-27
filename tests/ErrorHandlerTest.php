<?php

namespace Redaxo\Core\Tests;

use Override;
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

    public function testGetThrowErrorLevelsDefaultsByMode(): void
    {
        unset($_SERVER['REX_ERROR_THROW']);

        $_SERVER['REX_MODE'] = 'dev';
        self::assertSame(E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE, ErrorHandler::getThrowErrorLevels());

        $_SERVER['REX_MODE'] = 'live';
        self::assertSame(0, ErrorHandler::getThrowErrorLevels());

        $_SERVER['REX_MODE'] = 'hardened';
        self::assertSame(0, ErrorHandler::getThrowErrorLevels());
    }

    public function testGetThrowErrorLevelsNoneAndAll(): void
    {
        $_SERVER['REX_MODE'] = 'dev';
        $_SERVER['REX_ERROR_THROW'] = 'none';
        self::assertSame(0, ErrorHandler::getThrowErrorLevels());

        $_SERVER['REX_MODE'] = 'live';
        $_SERVER['REX_ERROR_THROW'] = 'all';
        self::assertSame(E_ALL, ErrorHandler::getThrowErrorLevels());
    }

    public function testGetThrowErrorLevelsConstantNames(): void
    {
        $_SERVER['REX_MODE'] = 'live';

        $_SERVER['REX_ERROR_THROW'] = 'E_WARNING';
        self::assertSame(E_WARNING, ErrorHandler::getThrowErrorLevels());

        $_SERVER['REX_ERROR_THROW'] = 'E_WARNING,E_NOTICE';
        self::assertSame(E_WARNING | E_NOTICE, ErrorHandler::getThrowErrorLevels());

        $_SERVER['REX_ERROR_THROW'] = ' E_WARNING , E_DEPRECATED ';
        self::assertSame(E_WARNING | E_DEPRECATED, ErrorHandler::getThrowErrorLevels());
    }

    public function testGetThrowErrorLevelsUnknownConstant(): void
    {
        $_SERVER['REX_ERROR_THROW'] = 'E_WARNING,E_FOO';

        $this->expectException(LogicException::class);
        ErrorHandler::getThrowErrorLevels();
    }

    public function testGetThrowErrorLevelsInvalidFormat(): void
    {
        $_SERVER['REX_ERROR_THROW'] = 'warning';

        $this->expectException(LogicException::class);
        ErrorHandler::getThrowErrorLevels();
    }
}
