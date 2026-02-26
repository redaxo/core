<?php

/**
 * @package redaxo\structure\content
 */
final class rex_ctype
{
    /** @param positive-int $id */
    private function __construct(
        private int $id,
        private string $name,
    ) {}

    /** @return positive-int */
    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<rex_ctype> */
    public static function forTemplate(int $templateId): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT attributes FROM ' . rex::getTable('template') . ' WHERE id = ?', [$templateId]);
        $attributes = $sql->getArrayValue('attributes');

        /** @var array<positive-int, string> $ctypesData */
        $ctypesData = $attributes['ctype'] ?? [];

        $ctypes = [];
        foreach ($ctypesData as $id => $name) {
            $ctypes[] = new self($id, $name);
        }
        return $ctypes;
    }
}
