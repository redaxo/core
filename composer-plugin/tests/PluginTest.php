<?php

namespace Redaxo\ComposerPlugin\Tests;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\BufferIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redaxo\ComposerPlugin\Plugin;

use function count;

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

    /**
     * @param 'setup'|'migrate'|null $expectedHint
     * @param list<string> $expectedRemovedAddons
     * @param list<OperationInterface> $operations
     */
    #[DataProvider('provideHints')]
    public function testHints(?string $expectedHint, array $expectedRemovedAddons, array $operations): void
    {
        $composer = new Composer();
        $composer->setPackage(new RootPackage('acme/project', '1.0.0.0', '1.0.0'));
        $io = new BufferIO();

        $plugin = new Plugin();
        foreach ($operations as $operation) {
            $plugin->onPackageChange(new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $composer, $io, true, new InstalledArrayRepository(), $operations, $operation));
        }
        $plugin->onPostInstallOrUpdate(new Event(ScriptEvents::POST_UPDATE_CMD, $composer, $io));

        $output = $io->getOutput();

        match ($expectedHint) {
            'setup' => self::assertStringContainsString('Run `php bin/console setup` to set up a new installation', $output),
            'migrate' => self::assertStringContainsString('Run `php bin/console migrate` to bring the database in line', $output),
            null => self::assertStringNotContainsString('migrate', $output),
        };
        if ('setup' !== $expectedHint) {
            self::assertStringNotContainsString('setup', $output);
        }

        self::assertSame(count($expectedRemovedAddons), substr_count($output, 'has been removed'));
        foreach ($expectedRemovedAddons as $addon) {
            self::assertStringContainsString('The addon "' . $addon . '" has been removed', $output);
            self::assertStringContainsString('`php bin/console addon:uninstall ' . $addon . '`', $output);
        }

        // the state is reset for further composer runs within the same process
        $io = new BufferIO();
        $plugin->onPostInstallOrUpdate(new Event(ScriptEvents::POST_UPDATE_CMD, $composer, $io));
        self::assertSame('', $io->getOutput());
    }

    /** @return iterable<string, array{'setup'|'migrate'|null, list<string>, list<OperationInterface>}> */
    public static function provideHints(): iterable
    {
        $core = new Package('redaxo/core', '6.0.0.0', '6.0.0');
        $addon = new Package('acme/foo', '1.0.0.0', '1.0.0');
        $addon->setType('redaxo-addon');
        $library = new Package('acme/lib', '1.0.0.0', '1.0.0');

        yield 'nothing changed' => [null, [], []];
        yield 'other package' => [null, [], [new InstallOperation($library), new UninstallOperation($library)]];
        yield 'fresh installation' => ['setup', [], [new InstallOperation($library), new InstallOperation($core), new InstallOperation($addon)]];
        yield 'core updated' => ['migrate', [], [new UpdateOperation($core, $core)]];
        yield 'addon installed' => ['migrate', [], [new InstallOperation($addon)]];
        yield 'addon removed' => ['migrate', ['foo'], [new InstallOperation($library), new UninstallOperation($addon)]];
    }
}
