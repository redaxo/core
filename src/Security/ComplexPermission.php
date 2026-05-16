<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Select\Select;

use function is_array;
use function sprintf;

/**
 * Abstract class for complex permissions.
 *
 * All permission check methods ("hasPerm()" etc.) in child classes should return "true" for admins
 *
 * @template-covariant TPermission of string|int = string|int
 */
abstract class ComplexPermission
{
    final public const string ALL = 'all';

    /** @var array<string, class-string<ComplexPermission>> */
    private static array $classes = [];

    /** @var list<TPermission>|self::ALL */
    final protected readonly string|array $perms;

    /** @param list<string>|self::ALL $perms */
    protected function __construct(
        /** @final */ protected readonly User $user,
        array|string $perms,
    ) {
        if (is_array($perms)) {
            /** @var list<TPermission> $perms */
            $perms = array_map(static fn (string $perm) => (string) (int) $perm === $perm ? (int) $perm : $perm, $perms);
        }
        $this->perms = $perms;
    }

    /**
     * Returns if the user has the permission for all items.
     *
     * @phpstan-assert-if-false !string $this->perms
     */
    final public function hasAll(): bool
    {
        return $this->user->admin || self::ALL === $this->perms;
    }

    /**
     * Returns the field params for the role form.
     *
     * @return array{label: string, all_label: string, select?: Select, options?: array<string|int, string>, sql_options?: string}|null
     */
    public static function getFieldParams(): ?array
    {
        return null;
    }

    /**
     * Registers a new complex perm class.
     *
     * @param string $key Key for the complex perm
     * @param class-string<self> $class Class name
     */
    final public static function register(string $key, string $class): void
    {
        if (!is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" must be a subclass of "%s".', $class, self::class));
        }
        self::$classes[$key] = $class;
    }

    /**
     * Returns all complex perm classes.
     *
     * @return array<string, class-string<ComplexPermission>> Class names
     */
    final public static function getAll(): array
    {
        return self::$classes;
    }

    /** @param list<string>|self::ALL $perms Permissions */
    final public static function get(User $user, string $key, array|string $perms = []): ?self
    {
        if (!isset(self::$classes[$key])) {
            return null;
        }
        $class = self::$classes[$key];
        return new $class($user, $perms);
    }

    /** Should be called if an item is removed. */
    final public static function removeItem(string $key, int|string $item): void
    {
        Extension::registerPoint(new ExtensionPoint('COMPLEX_PERM_REMOVE_ITEM', '', ['key' => $key, 'item' => $item], true));
    }

    /** Should be called if an item is replaced. */
    final public static function replaceItem(string $key, int|string $item, int|string $new): void
    {
        Extension::registerPoint(new ExtensionPoint('COMPLEX_PERM_REPLACE_ITEM', '', ['key' => $key, 'item' => $item, 'new' => $new], true));
    }
}
