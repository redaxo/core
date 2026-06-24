<?php

namespace Redaxo\Core\Field;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Http\Request;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;

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
 * A choice among a fixed set of options, rendered as a select, multi-select, radio group or checkbox
 * group depending on the `expanded`/`multiple` flags (à la Symfony's ChoiceType):
 *
 * | expanded | multiple | control        |
 * |----------|----------|----------------|
 * | false    | false    | select         |
 * | false    | true     | multi-select   |
 * | true     | false    | radio buttons  |
 * | true     | true     | checkbox group |
 *
 * Single values are stored as-is, multiple values pipe-delimited (`|a|b|`). Pass a static set via the
 * `$choices` constructor argument, or override {@see self::choices()} to provide them dynamically.
 * Choices may be grouped by nesting: a value that is itself an array becomes an optgroup (its key the
 * group label).
 */
class ChoiceField extends Field implements ProvidesColumn
{
    /**
     * @param array<int|string, string|array<int|string, string>> $choices the selectable choices as
     *     `value => label`, or `group label => [value => label]` for grouping
     */
    public function __construct(
        string $name,
        string $label = '',
        protected readonly array $choices = [],
        public readonly bool $multiple = false,
        public readonly bool $expanded = false,
        ?string $note = null,
        bool $required = false,
        ?string $default = null,
        HtmlAttributes $attributes = new HtmlAttributes(),
    ) {
        parent::__construct($name, $label, $note, $required, $default, $attributes);
    }

    /**
     * The selectable choices. Returns the constructor choices; override to provide them dynamically.
     *
     * @return iterable<int|string, string|array<int|string, string>>
     */
    public function choices(): iterable
    {
        return $this->choices;
    }

    /** @return list<string> the currently selected values */
    public function selected(): array
    {
        return $this->multiple
            ? self::splitMultiple((string) $this->binding->value)
            : [(string) $this->binding->value];
    }

    /**
     * The `<option>`/`<optgroup>` markup for the (multi-)select rendering. The recursion lives here
     * rather than in the view because it is logic, not layout.
     */
    public function renderOptions(): Html
    {
        return Html::raw($this->buildOptions($this->choices(), $this->selected()));
    }

    /** The radio/checkbox items for the expanded rendering, including nested fieldsets for groups. */
    public function renderExpandedItems(): Html
    {
        $type = $this->multiple ? 'checkbox' : 'radio';
        $nameAttr = $this->multiple ? $this->binding->name . '[]' : $this->binding->name;

        return Html::raw($this->buildExpanded($this->choices(), $this->selected(), $type, $nameAttr));
    }

    /**
     * @param iterable<int|string, string|array<int|string, string>> $choices
     * @param list<string> $selected
     */
    private function buildOptions(iterable $choices, array $selected): string
    {
        $html = '';
        foreach ($choices as $value => $label) {
            if (is_array($label)) {
                $html .= sprintf('<optgroup label="%s">%s</optgroup>', escape((string) $value), $this->buildOptions($label, $selected));
                continue;
            }

            $attributes = new HtmlAttributes([
                'value' => (string) $value,
                'selected' => in_array((string) $value, $selected, true),
            ]);

            $html .= sprintf('<option %s>%s</option>', $attributes, escape($label));
        }

        return $html;
    }

    /**
     * @param iterable<int|string, string|array<int|string, string>> $choices
     * @param list<string> $selected
     */
    private function buildExpanded(iterable $choices, array $selected, string $type, string $nameAttr): string
    {
        $html = '';
        foreach ($choices as $value => $label) {
            if (is_array($label)) {
                // The semantic equivalent of a <select> optgroup for radios/checkboxes is a nested fieldset.
                $html .= sprintf(
                    '<fieldset class="rex-choice-group"><legend>%s</legend>%s</fieldset>',
                    escape((string) $value),
                    $this->buildExpanded($label, $selected, $type, $nameAttr),
                );
                continue;
            }

            $id = $this->binding->name . '_' . (string) preg_replace('/[^A-Za-z0-9_]/', '_', (string) $value);

            $attributes = new HtmlAttributes([
                'type' => $type,
                'name' => $nameAttr,
                'id' => $id,
                'value' => (string) $value,
                'checked' => in_array((string) $value, $selected, true),
            ]);

            $html .= sprintf(
                '<div class="%s"><label for="%s"><input %s> %s</label></div>',
                escape($type),
                escape($id),
                $attributes,
                escape($label),
            );
        }

        return $html;
    }

    public function parseRequest(string $name): mixed
    {
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

    public function column(string $name): Column
    {
        return new Column($name, 'varchar(255)', nullable: true, default: $this->default);
    }

    /** @return list<string> */
    public static function splitMultiple(string $stored): array
    {
        return array_values(array_filter(explode('|', trim($stored, '|')), static fn (string $v): bool => '' !== $v));
    }
}
