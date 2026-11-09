<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ClassDiscovery;
use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Form\Field\Field;
use Redaxo\Core\Form\Field\ReferencesItems;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Form\Reference;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\Translatable;
use WeakMap;

use function sprintf;

/**
 * Base class of the modules, annotated with {@see AsModule}.
 *
 * An instance represents the module of one slice, see {@see self::of()}. What concerns the module as a whole
 * (key, name, where it is allowed) is static.
 *
 * The fields of the module are its properties, annotated with form field types (see {@see Field}), and stored as JSON
 * in the slice, each under its key: the property name in snake_case, or the `key` of the field, e.g. to keep the
 * values of a renamed property. The backend form shows all fields by default, and the output reads the properties:
 *
 * ```php
 * #[AsModule('text', 'Text')]
 * final class TextModule extends Module
 * {
 *     #[Text('Headline', required: true)]
 *     public string $headline = '';
 *
 *     public function output(): string
 *     {
 *         return '<h2>' . escape($this->headline) . '</h2>';
 *     }
 * }
 * ```
 */
abstract class Module
{
    /** Name of the request parameter holding the submitted field values. */
    private const string FORM_NAME = 'slice';

    /** @var array<string, class-string<self>>|null keyed by module key */
    private static ?array $classes = null;

    /** @var array<class-string<self>, AsModule> */
    private static array $attributes = [];

    /** @var WeakMap<ArticleSlice, self>|null */
    private static ?WeakMap $instances = null;

    final public function __construct(
        public readonly ArticleSlice $slice,
    ) {}

    /**
     * Returns the module of the given slice, holding its stored field values.
     *
     * Called on a concrete module class, the slice must belong to that module.
     * The instance is shared per slice, changes to it are not persisted.
     */
    final public static function of(ArticleSlice $slice): static
    {
        $class = self::getClass($slice->moduleKey)
            ?? throw new LogicException(sprintf('The module "%s" of slice %d does not exist.', $slice->moduleKey, $slice->id));

        if (!is_a($class, static::class, true)) {
            throw new LogicException(sprintf('Slice %d belongs to module %s, not to %s.', $slice->id, $class, static::class));
        }

        if (null === self::$instances) {
            /** @var WeakMap<ArticleSlice, self> $map */
            $map = new WeakMap();
            self::$instances = $map;
        }
        $instances = self::$instances;

        if (!isset($instances[$slice])) {
            $module = new $class($slice);
            FormSchema::of($class)->hydrate($module, $slice->data);
            $instances[$slice] = $module;
        }

        /** @var static */
        return $instances[$slice];
    }

    /** @return array<string, class-string<self>> keyed by module key */
    final public static function getAll(): array
    {
        if (null !== self::$classes) {
            return self::$classes;
        }

        $classes = [];
        foreach (ClassDiscovery::getInstance()->discoverByAttribute(AsModule::class, self::class) as $class => $attribute) {
            $classes[$attribute->key] = $class;
            self::$attributes[$class] = $attribute;
        }

        return self::$classes = $classes;
    }

    /** @return class-string<self>|null */
    final public static function getClass(string $key): ?string
    {
        return self::getAll()[$key] ?? null;
    }

    final public static function exists(string $key): bool
    {
        return null !== self::getClass($key);
    }

    /** Unique key, used as DB reference in rex_article_slice.module. */
    final public static function key(): string
    {
        return self::attribute()->key;
    }

    final public static function name(): string
    {
        return Translatable::text(self::attribute()->name);
    }

    /**
     * Whether this module is allowed in the given category.
     * `null` means root level (articles without a category).
     * The default implementation allows the module everywhere.
     *
     * Override this to restrict to specific categories.
     */
    public static function isAllowedInCategory(?Category $category): bool
    {
        return true;
    }

    /**
     * Renders the backend edit form.
     *
     * The default renders all fields; override it for a custom layout, using {@see Form::row()} etc.
     */
    public function input(Form $form): string
    {
        return (string) $form->rest();
    }

    /**
     * Render the frontend output.
     *
     * @throws ArticleNotFoundException if rendering should be aborted to switch to the not found article
     */
    abstract public function output(): string;

    /**
     * Cross-field validation, called after all fields are valid and written into the instance.
     *
     * Return an error message to reject, or `null` if valid.
     */
    public function validate(): string|Translatable|null
    {
        return null;
    }

    /** Called after a successful validation, right before the values are saved, e.g. to derive values. */
    public function beforeSave(): void {}

    /** Called on a fresh instance of the saved slice, e.g. for side effects needing the id of a new slice. */
    public function afterSave(): void {}

    /**
     * Creates the form editing the fields of this module.
     *
     * @internal
     */
    final public function createForm(): Form
    {
        return new Form($this, name: self::FORM_NAME, csrf: false);
    }

    /**
     * Submits the field values of the current request into the given form, followed by {@see self::validate()}.
     *
     * @return bool whether all values are valid
     * @internal
     */
    final public function submitForm(Form $form): bool
    {
        /** @var array<array-key, mixed> $values */
        $values = Request::post(self::FORM_NAME, 'array', []);

        if (!$form->submit($values)) {
            return false;
        }

        $error = $this->validate();
        if (null !== $error) {
            $form->addError($error);

            return false;
        }

        return true;
    }

    /**
     * Encodes the field values for the `data` column of the slice; `null` for a module without fields.
     *
     * @internal
     */
    final public function encodeData(): ?string
    {
        return ArticleSlice::encodeData(FormSchema::of(static::class)->encode($this));
    }

    /**
     * Returns the items the field values refer to, see {@see ReferencesItems}.
     *
     * @return list<Reference>
     * @internal
     */
    final public function references(): array
    {
        return FormSchema::of(static::class)->references($this);
    }

    private static function attribute(): AsModule
    {
        self::getAll();

        return self::$attributes[static::class] ?? throw new LogicException(sprintf('%s is not a registered module.', static::class));
    }
}
