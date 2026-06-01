<?php

namespace Redaxo\Core\Filesystem;

use Redaxo\Core\AbstractProject;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Environment;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Util\Type;

use const DIRECTORY_SEPARATOR;

/**
 * Utility class to generate absolute paths.
 */
class DefaultPathProvider
{
    /** @var non-empty-string */
    protected readonly string $base;
    protected readonly string $frontend;
    protected readonly string $backend;
    /** @var non-empty-string */
    protected readonly string $core;
    protected readonly bool $provideAbsolutes;

    /**
     * Initializes the class.
     *
     * @param bool $provideAbsolutes Flag whether to return absolute path, or relative ones
     */
    public function __construct(AbstractProject $project, bool $provideAbsolutes)
    {
        if ($provideAbsolutes) {
            $this->base = Type::string(realpath($project->projectPath)) . '/';
            $this->frontend = 'public/';
            $this->backend = $project->backendDirectory;
            $this->core = Type::string(realpath($project->corePath)) . '/';
        } else {
            $this->base = Environment::Frontend === $project->environment ? './' : '../';
            $this->frontend = '';
            $this->backend = str_ends_with($this->base, '../') ? '' : $this->base . $project->backendDirectory . '/';
            $this->core = $this->base;
        }
        $this->provideAbsolutes = $provideAbsolutes;
    }

    /**
     * Returns the base/root path.
     *
     * @return non-empty-string
     *
     * @psalm-taint-specialize
     */
    public function base(string $file): string
    {
        if ($this->provideAbsolutes) {
            return strtr($this->base . $file, '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        }
        return $this->base . $file;
    }

    /**
     * Returns the path to the frontend (the document root).
     *
     * @return non-empty-string
     */
    public function frontend(string $file): string
    {
        return $this->base($this->frontend . $file);
    }

    /**
     * Returns the path to the frontend-controller (index.php from frontend).
     *
     * @return non-empty-string
     */
    public function frontendController(): string
    {
        return $this->base('index.php');
    }

    /**
     * Returns the path to the backend (folder where the backend controller is placed).
     *
     * @return non-empty-string
     *
     * @psalm-taint-specialize
     */
    public function backend(string $file = ''): string
    {
        if ($this->provideAbsolutes) {
            return $this->frontend($this->backend . '/' . $file);
        }

        if ('' === $this->backend . $file) {
            throw new InvalidArgumentException('Empty path given.');
        }
        return $this->backend . $file;
    }

    /**
     * Returns the path to the backend-controller (index.php from backend).
     *
     * @return non-empty-string
     */
    public function backendController(): string
    {
        return $this->backend('index.php');
    }

    /**
     * Returns the path to the media-folder.
     *
     * @return non-empty-string
     */
    public function media(string $file): string
    {
        return $this->frontend('media/' . $file);
    }

    /**
     * Returns the path to the assets folder.
     *
     * @return non-empty-string
     */
    public function assets(string $file): string
    {
        return $this->frontend('assets/' . $file);
    }

    /**
     * Returns the path to the assets folder of the core, which contains all assets required by the core to work properly.
     *
     * @return non-empty-string
     */
    public function coreAssets(string $file): string
    {
        return $this->assets('core/' . $file);
    }

    /**
     * Returns the path to the assets folder of the given addon, which contains all assets required by the addon to work properly.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public function addonAssets(string $addon, string $file): string
    {
        return $this->assets('addons/' . $addon . '/' . $file);
    }

    /**
     * Returns the path to the bin folder.
     *
     * @return non-empty-string
     */
    public function bin(string $file): string
    {
        return $this->base('bin/' . $file);
    }

    /**
     * Returns the path to the var folder.
     *
     * @return non-empty-string
     */
    public function var(string $file): string
    {
        return $this->base('var/' . $file);
    }

    /**
     * Returns the path to the data folder.
     *
     * @return non-empty-string
     */
    public function data(string $file): string
    {
        return $this->var('data/' . $file);
    }

    /**
     * Returns the path to the data folder of the core.
     *
     * @return non-empty-string
     */
    public function coreData(string $file): string
    {
        return $this->data('core/' . $file);
    }

    /**
     * Returns the path to the data folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public function addonData(string $addon, string $file): string
    {
        return $this->data('addons/' . $addon . '/' . $file);
    }

    /**
     * Returns the path to the log folder.
     *
     * @return non-empty-string
     */
    public function log(string $file): string
    {
        return $this->var('log/' . $file);
    }

    /**
     * Returns the path to the cache folder.
     *
     * @return non-empty-string
     */
    public function cache(string $file): string
    {
        return $this->var('cache/' . $file);
    }

    /**
     * Returns the path to the cache folder of the core.
     *
     * @return non-empty-string
     */
    public function coreCache(string $file): string
    {
        return $this->cache('core/' . $file);
    }

    /**
     * Returns the path to the cache folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     */
    public function addonCache(string $addon, string $file): string
    {
        return $this->cache('addons/' . $addon . '/' . $file);
    }

    /**
     * Returns the path to the base core folder.
     *
     * @return non-empty-string
     *
     * @psalm-taint-specialize
     */
    public function core(string $file): string
    {
        if (!$this->provideAbsolutes) {
            throw new LogicException('Source paths are only available for absolute paths.');
        }

        return strtr($this->core . $file, '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
    }

    /**
     * Returns the base path to the folder of the given addon.
     *
     * @param non-empty-string $addon
     * @return non-empty-string
     *
     * @psalm-taint-specialize
     */
    public function addon(string $addon, string $file): string
    {
        if (!$this->provideAbsolutes) {
            throw new LogicException('Source paths are only available for absolute paths.');
        }

        return strtr(Addon::require($addon)->path . '/' . $file, '/\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
    }
}
