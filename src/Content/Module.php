<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Filesystem\File;

use function assert;

class Module
{
    private int $id;
    private ?string $key = '';

    public function __construct(int $moduleId)
    {
        $this->id = $moduleId;
    }

    public static function forKey(string $moduleKey): ?self
    {
        $mapping = self::getKeyMapping();

        if (false !== $id = array_search($moduleKey, $mapping, true)) {
            $module = new self($id);
            $module->key = $moduleKey;

            return $module;
        }

        return null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        // key will never be empty string in the db
        if ('' === $this->key) {
            $this->key = self::getKeyMapping()[$this->id] ?? null;
            assert('' !== $this->key);
        }

        return $this->key;
    }

    /** @return array<int, string> */
    private static function getKeyMapping(): array
    {
        static $mapping;

        if (null !== $mapping) {
            return $mapping;
        }

        $file = ModuleCache::getKeyMappingPath();
        $mapping = File::getCache($file, null);

        if (null !== $mapping) {
            return $mapping;
        }

        ModuleCache::generateKeyMapping();

        return $mapping = File::getCache($file);
    }
}
