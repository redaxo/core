<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;

final readonly class ContentSection
{
    private function __construct(
        /** @var positive-int */
        public int $id,
        public string $name,
    ) {}

    /** @return list<self> */
    public static function forTemplate(int $templateId): array
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT attributes FROM ' . Core::getTable('template') . ' WHERE id = ?', [$templateId]);
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
