<?php

namespace Redaxo\Core\MetaInfo;

use LogicException;
use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\MetaInfo\Field\MetaField;

use function sprintf;

/**
 * Declarative definition of the meta fields of one {@see MetaEntity}.
 *
 * A project (or addon) defines one schema per entity, annotated with {@see AsMetaSchema}, and yields
 * its fields in display order. Schemas are discovered automatically; there is no DB-backed field
 * registry and no management UI anymore.
 */
abstract class MetaSchema
{
    /** @var array<string, self>|null keyed by MetaEntity case name */
    private static ?array $instances = null;

    /**
     * Yields the fields of this entity, in the order they should appear in the editor.
     *
     * Implementations may branch, loop or initialize freely.
     *
     * @return iterable<MetaField>
     */
    abstract public function fields(): iterable;

    /**
     * Cross-field validation. Receives all submitted values keyed by column name.
     *
     * Return an error message to reject, or `null` if valid.
     *
     * @param array<string, mixed> $values
     */
    public function validate(array $values): ?string
    {
        return null;
    }

    final public static function forEntity(MetaEntity $entity): ?self
    {
        return self::getAll()[$entity->name] ?? null;
    }

    /**
     * Returns the fields of the given entity in display order.
     *
     * @return list<MetaField>
     */
    final public static function getFields(MetaEntity $entity): array
    {
        $schema = self::forEntity($entity);

        if (null === $schema) {
            return [];
        }

        $fields = [];
        foreach ($schema->fields() as $field) {
            $fields[] = $field;
        }

        return $fields;
    }

    /** @return array<string, self> */
    private static function getAll(): array
    {
        if (null !== self::$instances) {
            return self::$instances;
        }

        $instances = [];
        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsMetaSchema::class, self::class) as $class => $attribute) {
            $entity = $attribute->entity->name;

            if (isset($instances[$entity])) {
                throw new LogicException(sprintf(
                    'Multiple meta schemas defined for entity "%s": "%s" and "%s". Only one schema per entity is supported.',
                    $entity,
                    $instances[$entity]::class,
                    $class,
                ));
            }

            $instances[$entity] = new $class();
        }

        return self::$instances = $instances;
    }
}
