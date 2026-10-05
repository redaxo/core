<?php

namespace Redaxo\ComposerPlugin\Tests;

use Composer\Composer;
use Composer\Package\RootPackage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\ComposerPlugin\Plugin;

/** @internal */
final class PluginTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $extra
     */
    #[DataProvider('provideConfigureRuntime')]
    public function testConfigureRuntime(array $expected, array $extra): void
    {
        $package = new RootPackage('acme/project', '1.0.0.0', '1.0.0');
        $package->setExtra($extra);

        $composer = new Composer();
        $composer->setPackage($package);

        Plugin::configureRuntime($composer);

        self::assertSame($expected, $package->getExtra());
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function provideConfigureRuntime(): iterable
    {
        yield 'no extra' => [
            ['runtime' => ['class' => 'Redaxo\Core\Runtime']],
            [],
        ];
        yield 'other runtime options' => [
            ['foo' => 'bar', 'runtime' => ['dotenv_path' => '.env.dist', 'class' => 'Redaxo\Core\Runtime']],
            ['foo' => 'bar', 'runtime' => ['dotenv_path' => '.env.dist']],
        ];
        yield 'own runtime class' => [
            ['runtime' => ['class' => 'Acme\Runtime']],
            ['runtime' => ['class' => 'Acme\Runtime']],
        ];
        yield 'runtime disabled' => [
            ['runtime' => false],
            ['runtime' => false],
        ];
    }
}
