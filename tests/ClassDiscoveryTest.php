<?php

namespace Redaxo\Core\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\File;
use ReflectionClass;
use ReflectionMethod;

use function realpath;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function var_export;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class ClassDiscoveryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = (string) realpath(sys_get_temp_dir()) . '/rex-class-discovery-' . uniqid();
    }

    protected function tearDown(): void
    {
        Dir::delete($this->dir);
    }

    public function testRelevantPathsSkipSymlinkedDependencies(): void
    {
        Dir::create($this->dir . '/src');
        Dir::create($this->dir . '/vendor/acme/lib/src');
        Dir::create($this->dir . '/linked/lib/src');
        Dir::create($this->dir . '/linked/skeleton/src');
        symlink($this->dir . '/linked/lib', $this->dir . '/vendor/acme/linked-lib');
        symlink($this->dir . '/linked/skeleton', $this->dir . '/vendor/acme/skeleton');

        File::put($this->dir . '/vendor/composer/installed.php', '<?php return ' . var_export([
            'root' => ['name' => 'acme/root'],
            'versions' => [
                'acme/root' => ['type' => 'project', 'install_path' => $this->dir . '/vendor/composer/../../'],
                'acme/lib' => ['type' => 'library', 'install_path' => $this->dir . '/vendor/composer/../acme/lib'],
                'acme/linked-lib' => ['type' => 'library', 'install_path' => $this->dir . '/vendor/composer/../acme/linked-lib'],
                'acme/skeleton' => ['type' => 'project', 'install_path' => $this->dir . '/vendor/composer/../acme/skeleton'],
                'acme/replaced' => ['replaced' => ['*']],
            ],
        ], true) . ';');

        $loader = new ClassLoader($this->dir . '/vendor');
        $loader->addPsr4('Acme\\Root\\', $this->dir . '/vendor/composer/../../src');
        $loader->addPsr4('Acme\\Lib\\', $this->dir . '/vendor/composer/../acme/lib/src');
        $loader->addPsr4('Acme\\LinkedLib\\', $this->dir . '/vendor/composer/../acme/linked-lib/src');
        $loader->addPsr4('Acme\\Skeleton\\', $this->dir . '/vendor/composer/../acme/skeleton/src');

        $discovery = new ReflectionClass(ClassDiscovery::class)->newInstanceWithoutConstructor();
        new ReflectionMethod(ClassDiscovery::class, '__construct')->invoke($discovery, $loader, $this->dir . '/vendor' . DIRECTORY_SEPARATOR);

        /** @var list<string> $paths */
        $paths = new ReflectionMethod(ClassDiscovery::class, 'getRelevantPaths')->invoke($discovery);

        self::assertContains($this->dir . '/src/', $paths);
        self::assertContains($this->dir . '/linked/skeleton/src/', $paths);
        self::assertNotContains($this->dir . '/vendor/acme/lib/src/', $paths);
        self::assertNotContains($this->dir . '/linked/lib/src/', $paths);
    }
}
