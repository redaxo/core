<?php

namespace Redaxo\Core\Form\Select;

use PDO;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;

use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

class Select
{
    /** @var array<string, int|string> */
    private array $attributes = [];
    private int $currentOptgroup = 0;
    /** @var array<int, string> */
    private array $optgroups = [];
    /** @var array<int, array<int, list<list{string, string|int, int, array<string, string|int>}>>> */
    private array $options = [];
    /** @var list<string> */
    private array $optionSelected = [];
    private int $optCount = 0;

    public function __construct()
    {
        $this->init();
    }

    public function init(): void
    {
        $this->resetSelected();
        $this->setName('standard');
        $this->setSize('1');
        $this->setMultiple(false);
        $this->setDisabled(false);
    }

    /** @param array<string, int|string> $attributes */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    public function setAttribute(string $name, string|int $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function delAttribute(string $name): bool
    {
        if ($this->hasAttribute($name)) {
            unset($this->attributes[$name]);
            return true;
        }
        return false;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function getAttribute(string $name, string|int $default = ''): string|int
    {
        if ($this->hasAttribute($name)) {
            return $this->attributes[$name];
        }
        return $default;
    }

    public function setMultiple(bool $multiple = true): void
    {
        if ($multiple) {
            $this->setAttribute('multiple', 'multiple');
            if ('1' == $this->getAttribute('size')) {
                $this->setSize('5');
            }
        } else {
            $this->delAttribute('multiple');
        }
    }

    public function setDisabled(bool $disabled = true): void
    {
        if ($disabled) {
            $this->setAttribute('disabled', 'disabled');
        } else {
            $this->delAttribute('disabled');
        }
    }

    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    public function setId(string $id): void
    {
        $this->setAttribute('id', $id);
    }

    /**
     * select style
     * Es ist moeglich sowohl eine Styleklasse als auch einen Style zu uebergeben.
     *
     * Aufrufbeispiel:
     * $sel_media->setStyle('class="inp100"');
     * und/oder
     * $sel_media->setStyle("width:150px;");
     */
    public function setStyle(string $style): void
    {
        if (str_contains($style, 'class=')) {
            if (preg_match('/class=["\']?([^"\']*)["\']?/i', $style, $matches)) {
                $this->setAttribute('class', $matches[1]);
            }
        } else {
            $this->setAttribute('style', $style);
        }
    }

    /** @param int|numeric-string $size */
    public function setSize(int|string $size): void
    {
        $this->setAttribute('size', $size);
    }

    /** @param string|int|list<string|int> $selected */
    public function setSelected(string|int|array $selected): void
    {
        if (is_array($selected)) {
            foreach ($selected as $sectvalue) {
                $this->setSelected($sectvalue);
            }
        } else {
            $this->optionSelected[] = (string) escape($selected);
        }
    }

    public function resetSelected(): void
    {
        $this->optionSelected = [];
    }

    public function addOptgroup(string $label): void
    {
        ++$this->currentOptgroup;
        $this->optgroups[$this->currentOptgroup] = $label;
    }

    public function endOptgroup(): void
    {
        ++$this->currentOptgroup;
    }

    /**
     * Fügt eine Option hinzu.
     * @param array<string, string|int> $attributes
     */
    public function addOption(string $name, string|int $value, int $id = 0, int $parentId = 0, array $attributes = []): void
    {
        $this->options[$this->currentOptgroup][$parentId][] = [$name, $value, $id, $attributes];
        ++$this->optCount;
    }

    /**
     * Fügt ein Array von Optionen hinzu, dass eine mehrdimensionale Struktur hat.
     *
     * @param array<string|int>|list<array{
     *     0: string, // name
     *     1: string|int, // value
     *     2?: int, // id
     *     3?: int, // parent id
     *     4?: bool, // selected
     *     5?: array<string, string|int> // attributes
     * }> $options
     */
    public function addOptions(array $options, bool $useOnlyValues = false): void
    {
        foreach ($options as $key => $option) {
            if (!is_array($option)) {
                $this->addOption((string) $option, $useOnlyValues ? $option : $key);

                continue;
            }

            $attributes = [];
            if (isset($option[5]) && is_array($option[5])) {
                $attributes = $option[5];
            }

            $this->addOption($option[0], $option[1], $option[2] ?? 0, $option[3] ?? 0, $attributes);

            if (isset($option[4]) && $option[4]) {
                $this->setSelected($option[1]);
            }
        }
    }

    /**
     * Fügt ein Array von Optionen hinzu, dass eine Key/Value Struktur hat.
     * Wenn $useKeys mit false, werden die Array-Keys mit den Array-Values überschrieben.
     * @param array<string|int, string> $options
     */
    public function addArrayOptions(array $options, bool $useKeys = true): void
    {
        foreach ($options as $key => $value) {
            if (!$useKeys) {
                $key = $value;
            }

            $this->addOption($value, $key);
        }
    }

    public function countOptions(): int
    {
        return $this->optCount;
    }

    /**
     * Fügt Optionen anhand der Übergeben SQL-Select-Abfrage hinzu.
     * @param positive-int $db
     */
    public function addSqlOptions(string $query, int $db = 1): void
    {
        $sql = Sql::factory($db);
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->addOptions($sql->getArray($query, [], PDO::FETCH_NUM)); // @phpstan-ignore argument.type
    }

    /**
     * Fügt Optionen anhand der Übergeben DBSQL-Select-Abfrage hinzu.
     *
     * @see Sql::setDBQuery()
     */
    public function addDBSqlOptions(string $query): void
    {
        $sql = Sql::factory();
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->addOptions($sql->getDBArray($query, [], PDO::FETCH_NUM)); // @phpstan-ignore argument.type
    }

    public function get(): string
    {
        $useRexSelectStyle = false;

        // RexSelectStyle im Backend nutzen
        if (Core::isBackend()) {
            $useRexSelectStyle = true;
        }
        // RexSelectStyle nicht nutzen, wenn die Klasse `.selectpicker` gesetzt ist
        if (isset($this->attributes['class']) && str_contains((string) $this->attributes['class'], 'selectpicker')) {
            $useRexSelectStyle = false;
        }
        // RexSelectStyle nicht nutzen, wenn das Selectfeld mehrzeilig ist
        if (isset($this->attributes['size']) && (int) $this->attributes['size'] > 1) {
            $useRexSelectStyle = false;
        }

        $attr = '';
        foreach ($this->attributes as $name => $value) {
            $attr .= ' ' . escape($name, 'html_attr') . '="' . escape($value) . '"';
        }

        $ausgabe = "\n";
        if ($useRexSelectStyle) {
            $ausgabe .= '<div class="rex-select-style">' . "\n";
        }
        $ausgabe .= '<select' . $attr . '>' . "\n";

        foreach ($this->options as $optgroup => $options) {
            $this->currentOptgroup = $optgroup;
            if ($optgroupLabel = $this->optgroups[$optgroup] ?? null) {
                $ausgabe .= '  <optgroup label="' . escape($optgroupLabel) . '">' . "\n";
            }
            if (is_array($options)) {
                $ausgabe .= $this->outGroup(0);
            }
            if ($optgroupLabel) {
                $ausgabe .= '  </optgroup>' . "\n";
            }
        }

        $ausgabe .= '</select>' . "\n";
        if ($useRexSelectStyle) {
            $ausgabe .= '</div>' . "\n";
        }

        return $ausgabe;
    }

    public function show(): void
    {
        echo $this->get();
    }

    protected function outGroup(int $parentId, int $level = 0): string
    {
        if ($level > 100) {
            // nur mal so zu sicherheit .. man weiss nie ;)
            throw new LogicException('Select->outGroup overflow.');
        }

        $ausgabe = '';
        $group = $this->getGroup($parentId);
        if (!is_array($group)) {
            return '';
        }
        foreach ($group as $option) {
            $name = $option[0];
            $value = $option[1];
            $id = $option[2];
            $attributes = [];
            if (isset($option[3]) && is_array($option[3])) {
                $attributes = $option[3];
            }
            $ausgabe .= $this->outOption($name, $value, $level, $attributes);

            $subgroup = $this->getGroup($id, true);
            if (false !== $subgroup) {
                $ausgabe .= $this->outGroup($id, $level + 1);
            }
        }
        return $ausgabe;
    }

    /** @param array<string, string|int> $attributes */
    protected function outOption(string $name, string|int $value, int $level = 0, array $attributes = []): string
    {
        $name = escape($name);
        // for BC reasons, we always expect value to be a string.
        // this also makes sure that the strict in_array() check below works.
        $value = (string) escape($value);

        $bsps = '';
        if ($level > 0) {
            $bsps = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
        }

        if (in_array($value, $this->optionSelected, true)) {
            $attributes['selected'] = 'selected';
        }

        $attr = '';
        foreach ($attributes as $n => $v) {
            $attr .= ' ' . escape($n, 'html_attr') . '="' . escape($v) . '"';
        }

        return '        <option value="' . $value . '"' . $attr . '>' . $bsps . $name . '</option>' . "\n";
    }

    /** @return false|list<list{string, string|int, int, array<string, string|int>}> */
    protected function getGroup(int $parentId, bool $ignoreMainGroup = false): array|false
    {
        if ($ignoreMainGroup && 0 == $parentId) {
            return false;
        }

        return $this->options[$this->currentOptgroup][$parentId] ?? false;
    }
}
