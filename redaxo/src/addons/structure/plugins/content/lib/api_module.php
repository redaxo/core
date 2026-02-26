<?php

/**
 * @package redaxo\structure\content
 */
class rex_module
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

        $file = rex_module_cache::getKeyMappingPath();
        $mapping = rex_file::getCache($file, null);

        if (null !== $mapping) {
            return $mapping;
        }

        rex_module_cache::generateKeyMapping();

        return $mapping = rex_file::getCache($file);
    }
}
