<?php

namespace Redaxo\Core\Tests\Backend;

use PHPUnit\Framework\TestCase;
use Redaxo\Core\Backend\Appearance;

/** @internal */
final class AppearanceTest extends TestCase
{
    public function testGetTheme(): void
    {
        $orig = Appearance::$forcedTheme;

        try {
            Appearance::$forcedTheme = 'dark';
            self::assertSame('dark', Appearance::getTheme());

            Appearance::$forcedTheme = 'nonsense';
            self::assertNull(Appearance::getTheme());

            Appearance::$forcedTheme = null;
            self::assertNull(Appearance::getTheme());
        } finally {
            Appearance::$forcedTheme = $orig;
        }
    }

    public function testGetInstanceColor(): void
    {
        $origServer = $_SERVER['REX_INSTANCE_COLOR'] ?? null;
        $origEnv = $_ENV['REX_INSTANCE_COLOR'] ?? null;

        try {
            $_SERVER['REX_INSTANCE_COLOR'] = '#3bb594';
            self::assertSame('#3bb594', Appearance::getInstanceColor());

            $_SERVER['REX_INSTANCE_COLOR'] = '';
            self::assertNull(Appearance::getInstanceColor());

            unset($_SERVER['REX_INSTANCE_COLOR'], $_ENV['REX_INSTANCE_COLOR']);
            self::assertNull(Appearance::getInstanceColor());
        } finally {
            if (null === $origServer) {
                unset($_SERVER['REX_INSTANCE_COLOR']);
            } else {
                $_SERVER['REX_INSTANCE_COLOR'] = $origServer;
            }

            if (null === $origEnv) {
                unset($_ENV['REX_INSTANCE_COLOR']);
            } else {
                $_ENV['REX_INSTANCE_COLOR'] = $origEnv;
            }
        }
    }
}
