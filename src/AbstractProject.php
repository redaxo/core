<?php

namespace Redaxo\Core;

use Override;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\DefaultPathProvider;
use ReflectionObject;
use Symfony\Component\Runtime\RunnerInterface;
use Throwable;

use function dirname;
use function sprintf;

use const STDERR;

abstract class AbstractProject implements RunnerInterface
{
    /** @var non-empty-string */
    public string $projectPath {
        get {
            if (isset($this->projectPath)) {
                return $this->projectPath;
            }

            $file = new ReflectionObject($this)->getFileName();
            if (!is_file($file)) {
                throw new LogicException(sprintf('Cannot auto-detect project base path for project of class "%s".', $this::class));
            }

            $dir = dirname($file);
            while (!is_file($dir . '/composer.json')) {
                if ($dir === dirname($dir)) {
                    throw new LogicException(sprintf('Cannot auto-detect project base path for project of class "%s".', $this::class));
                }

                $dir = dirname($dir);
            }

            return $this->projectPath = $dir;
        }
    }

    /** @var non-empty-string */
    public string $corePath {
        get => $this->corePath ??= $this->projectPath . '/vendor/redaxo/core';
    }

    /** @var non-empty-string */
    public string $backendDirectory = 'redaxo';

    public function __construct(
        /** @var 'frontend'|'backend'|'console' */
        public readonly string $environment,
    ) {}

    final public function bootCore(): void
    {
        if ('console' === $this->environment) {
            set_time_limit(0);

            // setup a minimal exception handler to print early errors,
            // happening before redaxo itself was able to register its ErrorHandler
            set_exception_handler(static function (Throwable $exception): void {
                fwrite(STDERR, $exception->getMessage() . "\n");
                fwrite(STDERR, $exception->getTraceAsString() . "\n");
                exit(254);
            });
        }

        $REX = [];
        $REX['REDAXO'] = 'frontend' !== $this->environment;
        $REX['PATH_PROVIDER'] = new DefaultPathProvider($this, true);
        $REX['URL_PROVIDER'] = new DefaultPathProvider($this, false);

        require dirname(__DIR__) . '/boot/core.php';
    }

    final public function bootAddons(): void
    {
        require dirname(__DIR__) . '/boot/addons.php';
    }

    #[Override]
    final public function run(): int
    {
        $this->bootCore();

        require dirname(__DIR__) . '/boot/' . $this->environment . '.php';

        return 0;
    }
}
