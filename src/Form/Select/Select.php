<?php

namespace Redaxo\Core\Form\Select;

use PDO;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;

use function count;
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
    public function setAttributes($attributes): void
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    /**
     * @param string $name
     * @param string|int $value
     */
    public function setAttribute($name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function delAttribute($name)
    {
        if ($this->hasAttribute($name)) {
            unset($this->attributes[$name]);
            return true;
        }
        return false;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @param string $name
     * @param string|int $default
     * @return string|int
     */
    public function getAttribute($name, $default = '')
    {
        if ($this->hasAttribute($name)) {
            return $this->attributes[$name];
        }
        return $default;
    }

    /** @param bool $multiple */
    public function setMultiple($multiple = true): void
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

    /** @param bool $disabled */
    public function setDisabled($disabled = true): void
    {
        if ($disabled) {
            $this->setAttribute('disabled', 'disabled');
        } else {
            $this->delAttribute('disabled');
        }
    }

    /** @param string $name */
    public function setName($name): void
    {
        $this->setAttribute('name', $name);
    }

    /** @param string $id */
    public function setId($id): void
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
     *
     * @param string $style
     */
    public function setStyle($style): void
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
    public function setSize($size): void
    {
        $this->setAttribute('size', $size);
    }

    /** @param string|int|list<string|int> $selected */
    public function setSelected($selected): void
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

    /** @param string $label */
    public function addOptgroup($label): void
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
     * @param string $name
     * @param string|int $value
     * @param int $id
     * @param int $parentId
     * @param array<string, string|int> $attributes
     */
    public function addOption($name, $value, $id = 0, $parentId = 0, array $attributes = []): void
    {
        $this->options[$this->currentOptgroup][$parentId][] = [$name, $value, $id, $attributes];
        ++$this->optCount;
    }

    /**
     * Fügt ein Array von Optionen hinzu, dass eine mehrdimensionale Struktur hat.
     *
     * Dim   Wert
     * 0.    Name
     * 1.    Value
     * 2.    Id
     * 3.    parent_id
     * 4.    Selected
     * 5.    Attributes
     *
     * @param bool $useOnlyValues
     */
    public function addOptions($options, $useOnlyValues = false): void
    {
        if (is_array($options) && count($options) > 0) {
            // Hier vorher auf is_array abfragen, da bei Strings auch die Syntax mit [] funktioniert
            // $ab = "hallo"; $ab[2] -> "l"
            $grouped = isset($options[0]) && is_array($options[0]) && isset($options[0][2]) && isset($options[0][3]);
            foreach ($options as $key => $option) {
                $option = (array) $option;
                $attributes = [];
                if (isset($option[5]) && is_array($option[5])) {
                    $attributes = $option[5];
                }
                if ($grouped) {
                    $this->addOption($option[0], $option[1], $option[2], $option[3], $attributes);
                    if (isset($option[4]) && $option[4]) {
                        $this->setSelected($option[1]);
                    }
                } else {
                    if ($useOnlyValues) {
                        $this->addOption($option[0], $option[0]);
                    } else {
                        if (!isset($option[1])) {
                            $option[1] = $key;
                        }

                        $this->addOption($option[0], $option[1]);
                    }
                }
            }
        }
    }

    /**
     * Fügt ein Array von Optionen hinzu, dass eine Key/Value Struktur hat.
     * Wenn $useKeys mit false, werden die Array-Keys mit den Array-Values überschrieben.
     * @param array<string|int, string> $options
     * @param bool $useKeys
     */
    public function addArrayOptions(array $options, $useKeys = true): void
    {
        foreach ($options as $key => $value) {
            if (!$useKeys) {
                $key = $value;
            }

            $this->addOption($value, $key);
        }
    }

    /** @return int */
    public function countOptions()
    {
        return $this->optCount;
    }

    /**
     * Fügt Optionen anhand der Übergeben SQL-Select-Abfrage hinzu.
     * @param string $query
     * @param positive-int $db
     */
    public function addSqlOptions($query, int $db = 1): void
    {
        $sql = Sql::factory($db);
        $this->addOptions($sql->getArray($query, [], PDO::FETCH_NUM));
    }

    /**
     * Fügt Optionen anhand der Übergeben DBSQL-Select-Abfrage hinzu.
     *
     * @see Sql::setDBQuery()
     * @param string $query
     */
    public function addDBSqlOptions($query): void
    {
        $sql = Sql::factory();
        $this->addOptions($sql->getDBArray($query, [], PDO::FETCH_NUM));
    }

    /** @return string */
    public function get()
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

    /**
     * @param int $parentId
     * @param int $level
     * @return string
     */
    protected function outGroup($parentId, $level = 0)
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

    /**
     * @param string $name
     * @param string|int $value
     * @param int $level
     * @param array<string, string|int> $attributes
     * @return string
     */
    protected function outOption($name, $value, $level = 0, array $attributes = [])
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

    /**
     * @param int $parentId
     * @param bool $ignoreMainGroup
     * @return false|list<list{string, string|int, int, array<string, string|int>}>
     */
    protected function getGroup($parentId, $ignoreMainGroup = false)
    {
        if ($ignoreMainGroup && 0 == $parentId) {
            return false;
        }

        return $this->options[$this->currentOptgroup][$parentId] ?? false;
    }
}
