<?php

namespace Redaxo\Core\Language;

use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;

use function count;
use function sprintf;

final class Language
{
    private static bool $cacheLoaded = false;
    /** @var array<int, self> */
    private static array $clangs = [];
    private static ?int $currentId = null;

    private function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly int $priority,
        private readonly bool $status,
        /** @var array<string, string|int|null> */
        private readonly array $additionalData,
    ) {}

    /** Checks if the given clang exists. */
    public static function exists(int $id): bool
    {
        self::checkCache();
        return isset(self::$clangs[$id]);
    }

    /** Returns the clang object for the given id. */
    public static function get(int $id): ?self
    {
        if (self::exists($id)) {
            return self::$clangs[$id];
        }
        return null;
    }

    /** Returns the clang object for the given id. */
    public static function require(int $id): self
    {
        if (self::exists($id)) {
            return self::$clangs[$id];
        }
        throw new RuntimeException(sprintf('Required language with ID "%s" does not exist.', $id));
    }

    public static function getStartId(): int
    {
        foreach (self::getAll() as $id => $clang) {
            return $id;
        }
        throw new LogicException('No language found.');
    }

    public static function getCurrent(): self
    {
        $clang = self::get(self::getCurrentId());

        if (!$clang) {
            throw new LogicException('Language with id "' . self::getCurrentId() . '" not found.');
        }

        return $clang;
    }

    public static function getCurrentId(): int
    {
        return self::$currentId ?? self::$currentId = self::getStartId();
    }

    public static function setCurrentId(int $id): void
    {
        if (!self::exists($id)) {
            throw new RuntimeException('Language id "' . $id . '" doesn\'t exist');
        }
        self::$currentId = $id;
    }

    public function isOnline(): bool
    {
        return $this->status;
    }

    /** Checks whether the language has the given value. */
    public function hasValue(string $key): bool
    {
        return null !== $this->getValue($key);
    }

    /** Returns the given value. */
    public function getValue(string $key): string|int|bool|null
    {
        $key = strtolower($key);

        return match ($key) {
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'priority' => $this->priority,
            'status' => $this->status,
            default => $this->additionalData[$key] ?? $this->additionalData['clang_' . $key] ?? null,
        };
    }

    /** Counts the clangs. */
    public static function count(bool $ignoreOfflines = false): int
    {
        self::checkCache();
        return count(self::getAll($ignoreOfflines));
    }

    /**
     * Returns an array of all clang ids.
     *
     * @return list<int>
     */
    public static function getAllIds(bool $ignoreOfflines = false): array
    {
        self::checkCache();
        return array_keys(self::getAll($ignoreOfflines));
    }

    /**
     * Returns an array of all clangs.
     *
     * @return array<int, self>
     */
    public static function getAll(bool $ignoreOfflines = false): array
    {
        self::checkCache();

        if (!$ignoreOfflines) {
            return self::$clangs;
        }

        return array_filter(self::$clangs, static function (self $clang) {
            return $clang->isOnline();
        });
    }

    /** Loads the cache if not already loaded. */
    private static function checkCache(): void
    {
        if (self::$cacheLoaded) {
            return;
        }

        $file = Path::coreCache('clang.cache');
        $cache = File::getCache($file);

        // deliberately no is_file() check: a parallel cache clear could delete the file between check and read
        if (!$cache) {
            $cache = LanguageHandler::generateCache();
        }

        /**
         * @var int $id
         * @var array<string, string|int|null> $data
         */
        foreach ($cache as $id => $data) {
            $getAndUnset = static function (string $key) use (&$data): mixed {
                $value = $data[$key];
                unset($data[$key]);
                return $value;
            };

            /** @psalm-suppress InvalidScalarArgument */
            $clang = new self(
                $getAndUnset('id'),
                $getAndUnset('code'),
                $getAndUnset('name'),
                $getAndUnset('priority'),
                $getAndUnset('status'),
                $data,
            );

            self::$clangs[$id] = $clang;
        }
        self::$cacheLoaded = true;
    }

    /** Resets the intern cache of this class. */
    public static function reset(): void
    {
        self::$cacheLoaded = false;
        self::$clangs = [];
    }
}
