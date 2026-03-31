<?php

namespace Redaxo\Core\Filesystem;

use Closure;
use Countable;
use FilesystemIterator;
use IteratorAggregate;
use Override;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Util\SortableIterator;
use SplFileInfo;
use Traversable;

use function is_array;

/**
 * @implements IteratorAggregate<string, SplFileInfo>
 */
class Finder implements IteratorAggregate, Countable
{
    use FactoryTrait;

    final public const string ALL = '__ALL__';

    private bool $recursive = false;
    /** @var RecursiveIteratorIterator::SELF_FIRST|RecursiveIteratorIterator::CHILD_FIRST|RecursiveIteratorIterator::LEAVES_ONLY */
    private int $recursiveMode = RecursiveIteratorIterator::SELF_FIRST;
    private bool $dirsOnly = false;
    /** @var list<string> */
    private array $ignoreFiles = [];
    /** @var list<string> */
    private array $ignoreFilesRecursive = [];
    /** @var list<string> */
    private array $ignoreDirs = [];
    /** @var list<string> */
    private array $ignoreDirsRecursive = [];
    private bool $ignoreSystemStuff = true;
    /** @var SortableIterator::*|Closure(mixed, mixed):int|null */
    private int|Closure|null $sort = null;

    final private function __construct(
        private readonly string $dir,
    ) {}

    /**
     * Returns a new finder object.
     *
     * @param string $dir Path to a directory
     */
    public static function factory(string $dir): static
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException('Directory "' . $dir . '" not found.');
        }

        $class = static::getFactoryClass();
        return new $class($dir);
    }

    /** Activate/Deactivate recursive directory scanning. */
    public function recursive(bool $recursive = true): static
    {
        $this->recursive = $recursive;

        return $this;
    }

    /** Fetch directory contents before recurse its subdirectories. */
    public function selfFirst(): static
    {
        $this->recursiveMode = RecursiveIteratorIterator::SELF_FIRST;

        return $this;
    }

    /** Fetch child directories before their parent directory. */
    public function childFirst(): static
    {
        $this->recursiveMode = RecursiveIteratorIterator::CHILD_FIRST;

        return $this;
    }

    /** Fetch files only. */
    public function filesOnly(): static
    {
        $this->recursiveMode = RecursiveIteratorIterator::LEAVES_ONLY;

        return $this;
    }

    /** Fetch dirs only. */
    public function dirsOnly(): static
    {
        $this->dirsOnly = true;

        return $this;
    }

    /**
     * Ignore all files which match the given glob pattern.
     *
     * @param string|list<string> $glob Glob pattern or an array of glob patterns
     * @param bool $recursive When FALSE the patterns won't be checked in child directories
     */
    public function ignoreFiles($glob, bool $recursive = true): static
    {
        $var = $recursive ? 'ignoreFilesRecursive' : 'ignoreFiles';
        if (is_array($glob)) {
            $this->$var += $glob;
        } else {
            $this->$var[] = $glob;
        }

        return $this;
    }

    /**
     * Ignore all directories which match the given glob pattern.
     *
     * @param string|list<string> $glob Glob pattern or an array of glob patterns
     * @param bool $recursive When FALSE the patterns won't be checked in child directories
     */
    public function ignoreDirs($glob, bool $recursive = true): static
    {
        $var = $recursive ? 'ignoreDirsRecursive' : 'ignoreDirs';
        if (is_array($glob)) {
            $this->$var += $glob;
        } else {
            $this->$var[] = $glob;
        }

        return $this;
    }

    /** Ignores system stuff (like .DS_Store, .svn, .git etc.). */
    public function ignoreSystemStuff(bool $ignoreSystemStuff = true): static
    {
        $this->ignoreSystemStuff = $ignoreSystemStuff;

        return $this;
    }

    /**
     * Sorts the elements.
     *
     * @param SortableIterator::*|Closure(mixed, mixed): int $sort Sort mode, see {@link SortableIterator::__construct()}
     */
    public function sort(int|Closure $sort = SortableIterator::KEYS): static
    {
        $this->sort = $sort;

        return $this;
    }

    /** @return Traversable<string, SplFileInfo> */
    #[Override]
    public function getIterator(): Traversable
    {
        /** @var RecursiveIterator<string, SplFileInfo> $iterator */
        $iterator = new RecursiveDirectoryIterator($this->dir, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);

        $iterator = new RecursiveCallbackFilterIterator($iterator, function (SplFileInfo $current, $key, $currentIterator) use ($iterator): bool {
            $filename = $current->getFilename();
            $isRoot = $currentIterator === $iterator;

            $match = static function ($pattern, $filename): int|false {
                $regex = '/^' . strtr(preg_quote($pattern, '/'), ['\*' => '.*', '\?' => '.']) . '$/i';
                return preg_match($regex, $filename);
            };

            if ($current->isFile()) {
                if ($this->dirsOnly) {
                    return false;
                }
                $ignoreFiles = $isRoot ? array_merge($this->ignoreFiles, $this->ignoreFilesRecursive) : $this->ignoreFilesRecursive;
                foreach ($ignoreFiles as $ignore) {
                    if ($match($ignore, $filename)) {
                        return false;
                    }
                }
            }

            if ($current->isDir()) {
                if (!$this->recursive && RecursiveIteratorIterator::LEAVES_ONLY === $this->recursiveMode) {
                    return false;
                }
                $ignoreDirs = $isRoot ? array_merge($this->ignoreDirs, $this->ignoreDirsRecursive) : $this->ignoreDirsRecursive;
                foreach ($ignoreDirs as $ignore) {
                    if ($match($ignore, $filename)) {
                        return false;
                    }
                }
            }

            if ($this->ignoreSystemStuff) {
                foreach (['.DS_Store', 'Thumbs.db', 'desktop.ini', '.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'] as $systemStuffFile) {
                    if (0 === stripos($filename, $systemStuffFile)) {
                        return false;
                    }
                }
            }

            return true;
        });

        if ($this->recursive) {
            /** @var Traversable<string, SplFileInfo> */
            $iterator = new RecursiveIteratorIterator($iterator, $this->recursiveMode);
        }

        if ($this->sort) {
            $iterator = new SortableIterator($iterator, $this->sort);
        }

        return $iterator;
    }

    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }
}
