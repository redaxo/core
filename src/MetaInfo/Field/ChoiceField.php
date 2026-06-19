<?php

namespace Redaxo\Core\MetaInfo\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function array_filter;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function preg_replace;
use function Redaxo\Core\View\escape;
use function sprintf;
use function trim;

/**
 * A choice among a fixed set of options, rendered as a select, multi-select, radio group or checkbox group
 * depending on the `expanded`/`multiple` flags (à la Symfony's ChoiceType):
 *
 * | expanded | multiple | control            |
 * |----------|----------|--------------------|
 * | false    | false    | select             |
 * | false    | true     | multi-select       |
 * | true     | false    | radio buttons      |
 * | true     | true     | checkbox group     |
 *
 * Single values are stored as-is, multiple values pipe-delimited (`|a|b|`).
 *
 * Pass a static set via the `$choices` constructor argument, or override {@see self::choices()} to provide them
 * dynamically (#1480). Choices may be grouped by nesting: a value that is itself an array becomes an optgroup
 * (its key being the group label).
 */
class ChoiceField extends MetaField
{
    /**
     * @param array<int|string, string|array<int|string, string>> $choices the selectable choices as `value => label`,
     *     or `group label => [value => label]` for grouping
     */
    public function __construct(
        string $name,
        string $label,
        protected readonly array $choices = [],
        public readonly bool $multiple = false,
        public readonly bool $expanded = false,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
    ) {
        parent::__construct($name, $label, $note, $required, $default);
    }

    /**
     * The selectable choices. Returns the choices passed to the constructor; override to provide them dynamically.
     *
     * @return iterable<int|string, string|array<int|string, string>>
     */
    public function choices(): iterable
    {
        return $this->choices;
    }

    public function column(MetaEntity $entity): ?Column
    {
        return new Column($this->columnName($entity), 'varchar(255)', nullable: true, default: $this->default);
    }

    public function parseRequest(MetaContext $context): int|string|null
    {
        $name = $this->columnName($context->entity);

        if (!$this->multiple) {
            return Request::post($name, 'string', '');
        }

        /** @var list<string> $values */
        $values = Request::post($name, 'array', []);

        return [] === $values ? '' : '|' . implode('|', $values) . '|';
    }

    /** @return string|list<string> the single value, or the list of values for `multiple` */
    public function format(mixed $stored): string|array
    {
        return $this->multiple ? self::splitMultiple((string) $stored) : (string) $stored;
    }

    public function renderInput(MetaContext $context): string
    {
        $name = $this->columnName($context->entity);
        $selected = $this->multiple ? self::splitMultiple((string) $context->value($this)) : [(string) $context->value($this)];

        if ($this->expanded) {
            return $this->renderExpanded($this->choices(), $selected, $name);
        }

        $nameAttr = $this->multiple ? $name . '[]' : $name;

        return sprintf(
            '<select class="form-control selectpicker" name="%s" id="%s"%s%s>%s</select>',
            escape($nameAttr),
            escape($name),
            $this->multiple ? ' multiple' : '',
            $this->required ? ' required' : '',
            $this->renderOptions($this->choices(), $selected),
        );
    }

    /**
     * @param iterable<int|string, string|array<int|string, string>> $choices
     * @param list<string> $selected
     */
    private function renderOptions(iterable $choices, array $selected): string
    {
        $html = '';
        foreach ($choices as $value => $label) {
            if (is_array($label)) {
                $html .= sprintf('<optgroup label="%s">%s</optgroup>', escape((string) $value), $this->renderOptions($label, $selected));
                continue;
            }

            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                escape((string) $value),
                in_array((string) $value, $selected, true) ? ' selected' : '',
                escape($label),
            );
        }

        return $html;
    }

    /**
     * @param iterable<int|string, string|array<int|string, string>> $choices
     * @param list<string> $selected
     */
    private function renderExpanded(iterable $choices, array $selected, string $name): string
    {
        $type = $this->multiple ? 'checkbox' : 'radio';
        $nameAttr = $this->multiple ? $name . '[]' : $name;

        $html = '';
        foreach ($choices as $value => $label) {
            if (is_array($label)) {
                $html .= sprintf('<p class="rex-choice-group"><strong>%s</strong></p>', escape((string) $value));
                $html .= $this->renderExpanded($label, $selected, $name);
                continue;
            }

            $id = $name . '_' . (string) preg_replace('/[^A-Za-z0-9_]/', '_', (string) $value);
            $html .= sprintf(
                '<div class="%s"><label for="%s"><input type="%s" name="%s" id="%2$s" value="%s"%s> %s</label></div>',
                $type,
                escape($id),
                $type,
                escape($nameAttr),
                escape((string) $value),
                in_array((string) $value, $selected, true) ? ' checked' : '',
                escape($label),
            );
        }

        return $html;
    }

    /** @return list<string> */
    private static function splitMultiple(string $stored): array
    {
        return array_values(array_filter(explode('|', trim($stored, '|')), static fn (string $v): bool => '' !== $v));
    }
}
