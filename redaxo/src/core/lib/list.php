<?php

// Nötige Konstanten
define('REX_LIST_OPT_SORT', 0);
define('REX_LIST_OPT_SORT_DIRECTION', 1);

/*
EXAMPLE:

$list = rex_list::factory('SELECT id,name FROM rex_article');
$list->setColumnFormat('id', 'date');
$list->setColumnLabel('name', 'Artikel-Name');
$list->setColumnSortable('name');
$list->addColumn('testhead','###id### - ###name###',-1);
$list->addColumn('testhead2','testbody2');
$list->setCaption('thomas macht das css');
$list->show();


EXAMPLE USING CUSTOM CALLBACKS WITH setColumnFormat() METHOD:

function callback_func($params)
{
    // $params['subject']  current value
    // $params['list']     rex_list object
    // $params['params']   custom params

    return $custom_string; // return value showed in list (note: no htmlspechialchars!)
}

// USING setColumnFormat() BY CALLING A FUNCTION
$list->setColumnFormat('id',                                     // field name
                                             'custom',                                 // format type
                                             'callback_func',                          // callback function name
                                                array('foo' => 'bar', '123' => '456')    // optional params for callback function
);

// USING setColumnFormat() BY CALLING CLASS & METHOD
$list->setColumnFormat('id',                                     // field name
                                             'custom',                                 // format type
                                                array('CLASS','METHOD'),                 // callback class/method name
                                                array('foo' => 'bar', '123' => '456')    // optional params for callback function
                                             );
*/

/**
 * Klasse zum erstellen von Listen.
 *
 * @package redaxo\core
 */
class rex_list implements rex_url_provider_interface
{
    use rex_factory_trait;

    public const DISABLE_PAGINATION = null;

    /** @var positive-int */
    private $db;

    protected rex_sql $sql;
    /** @var bool */
    private $debug;
    /** @var string */
    private $noRowsMessage;

    // --------- List Attributes
    /** @var string */
    private $name;
    /** @var array<string, string|int> */
    private array $params;
    private int $rows;

    // --------- Form Attributes
    /** @var array<string, string|int> */
    private array $formAttributes;

    //  --------- Row Attributes
    /** @var array<string, string|int>|callable(self):string */
    private $rowAttributes;

    // --------- Column Attributes
    /** @var array<string, string> */
    private array $customColumns;
    /** @var list<string> */
    private $columnNames;
    /** @var array<string, string> */
    private array $columnLabels;
    /** @var array<string, array{string, mixed, array<mixed>}> */
    private $columnFormates;
    /** @var array<string, array<string|int, mixed>> */
    private array $columnOptions;
    /** @var array<string, array{string, string}> */
    private array $columnLayouts;
    /** @var array<string, array> */
    private $columnParams;
    /** @var list<string> */
    private array $columnDisabled;

    // --------- Layout, Default
    /** @var array{string, string} */
    private array $defaultColumnLayout;

    // --------- Table Attributes
    /** @var string */
    private $caption;
    /** @var array<string, string|int> */
    private array $tableAttributes;
    /** @var array<int, array> */
    private $tableColumnGroups;

    // --------- Link Attributes
    /** @var array<string, array<string, string|int>> */
    private array $linkAttributes;

    // --------- Pagination Attributes
    private ?rex_pager $pager = null;

    /**
     * Erstellt ein rex_list Objekt.
     *
     * @param string $query SELECT Statement
     * @param int|self::DISABLE_PAGINATION $rowsPerPage
     * @param string|null $listName Name der Liste
     * @param bool $debug
     * @param positive-int $db
     * @param array<string, 'asc'|'desc'> $defaultSort
     */
    protected function __construct($query, $rowsPerPage = 30, $listName = null, $debug = false, $db = 1, array $defaultSort = [])
    {
        // --------- Validation
        if (!$listName) {
            // use a hopefully unique (per page) hash
            $listName = substr(md5($query), 0, 8);
        }

        // --------- List Attributes
        $this->db = $db;
        $this->sql = rex_sql::factory($db);
        $this->debug = $debug;
        $this->sql->setDebug($this->debug);
        $this->name = $listName;
        $this->caption = '';
        $this->rows = 0;
        $this->params = [];
        $this->tableAttributes = [];
        $this->noRowsMessage = rex_i18n::msg('list_no_rows');

        // --------- Form Attributes
        $this->formAttributes = [];

        // --------- Column Attributes
        $this->customColumns = [];
        $this->columnLabels = [];
        $this->columnFormates = [];
        $this->columnParams = [];
        $this->columnOptions = [];
        $this->columnLayouts = [];
        $this->columnDisabled = [];

        // --------- Default
        $this->defaultColumnLayout = ['<th>###VALUE###</th>', '<td data-title="###LABEL###">###VALUE###</td>'];

        // --------- Table Attributes
        $this->tableAttributes = [];
        $this->tableColumnGroups = [];

        // --------- Link Attributes
        $this->linkAttributes = [];

        // --------- Row Attributes
        $this->rowAttributes = [];

        // --------- Pagination Attributes
        if (self::DISABLE_PAGINATION !== $rowsPerPage) {
            $cursorName = $listName . '_start';
            if (null === rex_request($cursorName, 'int', null) && rex_request('start', 'int')) {
                // BC: Fallback to "start"
                $cursorName = 'start';
            }
            $this->pager = new rex_pager($rowsPerPage, $cursorName);

            $sql = rex_sql::factory($db);
            $sql->setQuery(self::prepareCountQuery($query));
            $this->rows = (int) $sql->getValue('rows');
            $this->pager->setRowCount($this->rows);
        }

        // --------- Load Data
        $this->sql->setQuery($this->prepareQuery($query, $defaultSort));
        if (self::DISABLE_PAGINATION === $rowsPerPage) {
            $this->rows = (int) $this->sql->getRows();
        }

        foreach ($this->sql->getFieldnames() as $columnName) {
            $this->columnNames[] = $columnName;
        }

        // --------- Load Env
        if (rex::isBackend()) {
            $this->loadBackendConfig();
        }

        $this->init();
    }

    /**
     * @param string $query
     * @param int|self::DISABLE_PAGINATION $rowsPerPage
     * @param string|null $listName
     * @param bool $debug
     * @param positive-int $db DB connection ID
     * @param array<string, 'asc'|'desc'> $defaultSort
     *
     * @return static
     */
    public static function factory($query, $rowsPerPage = 30, $listName = null, $debug = false, $db = 1, array $defaultSort = [])
    {
        $class = static::getFactoryClass();
        return new $class($query, $rowsPerPage, $listName, $debug, $db, $defaultSort);
    }

    /** @return void */
    public function init()
    {
        // nichts tun
    }

    // ---------------------- setters/getters

    /**
     * Gibt den Namen es Formulars zurück.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gibt eine Status Nachricht zurück.
     *
     * @return string
     */
    public function getMessage()
    {
        return rex_escape(rex_request($this->getName() . '_msg', 'string'));
    }

    /**
     * Gibt eine Warnung zurück.
     *
     * @return string
     */
    public function getWarning()
    {
        return rex_escape(rex_request($this->getName() . '_warning', 'string'));
    }

    /**
     * Setzt die Caption/den Titel der Tabelle
     * Gibt den Namen es Formulars zurück.
     *
     * @param string $caption Caption/Titel der Tabelle
     * @return void
     */
    public function setCaption($caption)
    {
        $this->caption = $caption;
    }

    /**
     * Gibt die Caption/den Titel der Tabelle zurück.
     *
     * @return string
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * @param string $message
     * @return void
     */
    public function setNoRowsMessage($message)
    {
        $this->noRowsMessage = $message;
    }

    /** @return string */
    public function getNoRowsMessage()
    {
        return $this->noRowsMessage;
    }

    /**
     * @param string $name
     * @param string|int $value
     * @return void
     */
    public function addParam($name, $value)
    {
        $this->params[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getParams()
    {
        return $this->params;
    }

    /** @return void */
    protected function loadBackendConfig()
    {
        $this->addParam('page', rex_be_controller::getCurrentPage());
    }

    /**
     * @param string $name
     * @param string|int $value
     * @return void
     */
    public function addTableAttribute($name, $value)
    {
        $this->tableAttributes[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getTableAttributes()
    {
        return $this->tableAttributes;
    }

    /**
     * @param string $name
     * @param string|int $value
     * @return void
     */
    public function addFormAttribute($name, $value)
    {
        $this->formAttributes[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getFormAttributes()
    {
        return $this->formAttributes;
    }

    /**
     * @param string $columnName
     * @param string $attrName
     * @param string|int $attrValue
     * @return void
     */
    public function addLinkAttribute($columnName, $attrName, $attrValue)
    {
        $this->linkAttributes[$columnName][$attrName] = $attrValue;
    }

    /** @return array<string, string|int>|null */
    public function getLinkAttributes($column, $default = null)
    {
        return $this->linkAttributes[$column] ?? $default;
    }

    // row attribute setter/getter

    /**
     * Methode, um der Zeile (<tr>) Attribute hinzuzufügen.
     *
     * @param array<string, string|int>|callable(self):string $attr Entweder ein array: [attributname => attribut, ...]
     *                                                              oder eine Callback-Funktion
     */
    public function setRowAttributes($attr): void
    {
        if (!is_array($attr) && !is_callable($attr)) {
            throw new InvalidArgumentException('$attr must be an array or a callable, but "' . get_debug_type($attr) . '" given');
        }

        $this->rowAttributes = $attr;
    }

    /**
     * Methode, um die Zeilen-Attribute (<tr>) abzufragen.
     *
     * @return array<string, string|int>|callable(self):string Entweder ein array: [attributname => attribut, ...]
     *                                                         oder eine Callback-Funktion
     */
    public function getRowAttributes()
    {
        return $this->rowAttributes;
    }

    // ---------------------- Column setters/getters/etc

    /**
     * Methode, um eine Spalte einzufügen.
     *
     * @param string $columnHead Titel der Spalte
     * @param string $columnBody Text/Format der Spalte
     * @param int $columnIndex Stelle, an der die neue Spalte erscheinen soll
     * @param array $columnLayout Layout der Spalte
     * @return void
     */
    public function addColumn($columnHead, $columnBody, $columnIndex = -1, $columnLayout = null)
    {
        // Bei negativem columnIndex, das Element am Ende anfügen
        if ($columnIndex < 0) {
            $columnIndex = count($this->columnNames);
        }

        array_splice($this->columnNames, $columnIndex, 0, [$columnHead]);
        $this->customColumns[$columnHead] = $columnBody;
        $this->setColumnLayout($columnHead, $columnLayout);
    }

    /**
     * Entfernt eine Spalte aus der Anzeige.
     *
     * @param string $columnName Name der Spalte
     * @return void
     */
    public function removeColumn($columnName)
    {
        $this->columnDisabled[] = $columnName;
    }

    /**
     * Methode, um das Layout einer Spalte zu setzen.
     *
     * @param string $columnHead Titel der Spalte
     * @param array $columnLayout Layout der Spalte
     * @return void
     */
    public function setColumnLayout($columnHead, $columnLayout)
    {
        $this->columnLayouts[$columnHead] = $columnLayout;
    }

    /**
     * Gibt das Layout einer Spalte zurück.
     *
     * @param string $columnName Name der Spalte
     *
     * @return array{string, string}
     */
    public function getColumnLayout($columnName)
    {
        if (isset($this->columnLayouts[$columnName]) && is_array($this->columnLayouts[$columnName])) {
            return $this->columnLayouts[$columnName];
        }

        return $this->defaultColumnLayout;
    }

    /**
     * Gibt die Layouts aller Spalten zurück.
     * @return array<string, array{string, string}>
     */
    public function getColumnLayouts()
    {
        return $this->columnLayouts;
    }

    /**
     * Gibt den Namen einer Spalte zurück.
     *
     * @param int $columnIndex Nummer der Spalte
     * @param mixed $default Defaultrückgabewert, falls keine Spalte mit der angegebenen Nummer vorhanden ist
     *
     * @return string|null
     */
    public function getColumnName($columnIndex, $default = null)
    {
        return $this->columnNames[$columnIndex] ?? $default;
    }

    /**
     * Gibt alle Namen der Spalten als Array zurück.
     *
     * @return list<string>
     */
    public function getColumnNames()
    {
        return $this->columnNames;
    }

    /** @return list<string> */
    protected function getEnabledColumnNames(): array
    {
        $columnNames = [];
        foreach ($this->getColumnNames() as $columnName) {
            if (!in_array($columnName, $this->columnDisabled)) {
                $columnNames[] = $columnName;
            }
        }

        return $columnNames;
    }

    /**
     * @param string $columnName
     * @param array{string, mixed, array<mixed>}|null $columnFormat
     * @return string
     */
    protected function getColumnValue($columnName, $columnFormat)
    {
        return $this->formatValue(
            $this->getValue($columnName),
            $columnFormat,
            !isset($this->customColumns[$columnName]),
            $columnName,
        );
    }

    /**
     * Setzt ein Label für eine Spalte.
     *
     * @param string $columnName Name der Spalte
     * @param string $label Label für die Spalte
     * @return void
     */
    public function setColumnLabel($columnName, $label)
    {
        $this->columnLabels[$columnName] = $label;
    }

    /**
     * Gibt das Label der Spalte zurück, falls gesetzt.
     *
     * Falls nicht vorhanden und der Parameter $default auf null steht,
     * wird der Spaltenname zurückgegeben
     *
     * @template T as null|string
     * @param string $columnName Name der Spalte
     * @param T $default Defaultrückgabewert, falls kein Label gesetzt ist
     * @return (T is null ? string : ?string)
     */
    public function getColumnLabel($columnName, $default = null)
    {
        return $this->columnLabels[$columnName] ?? $default ?? $columnName;
    }

    /**
     * Setzt ein Format für die Spalte.
     *
     * @param string $columnName Name der Spalte
     * @param string $formatType Formatierungstyp
     * @param mixed $format Zu verwendentes Format
     * @param array $params Custom params für callback func bei format_type 'custom'
     * @return void
     */
    public function setColumnFormat($columnName, $formatType, $format = '', array $params = [])
    {
        $this->columnFormates[$columnName] = [$formatType, $format, $params];
    }

    /**
     * Gibt das Format für eine Spalte zurück.
     *
     * @template T
     *
     * @param string $columnName Name der Spalte
     * @param T $default Defaultrückgabewert, falls keine Formatierung gesetzt ist
     *
     * @return array{string, mixed, array<mixed>}|T
     */
    public function getColumnFormat($columnName, $default = null)
    {
        return $this->columnFormates[$columnName] ?? $default;
    }

    /**
     * Markiert eine Spalte als sortierbar.
     *
     * @param string $columnName Name der Spalte
     * @param string $direction Startsortierrichtung der Spalte [ASC|DESC]
     * @return void
     */
    public function setColumnSortable($columnName, $direction = 'asc')
    {
        $this->setColumnOption($columnName, REX_LIST_OPT_SORT, true);
        $this->setColumnOption($columnName, REX_LIST_OPT_SORT_DIRECTION, strtolower($direction));
    }

    /**
     * Setzt eine Option für eine Spalte
     * (z.b. Sortable,..).
     *
     * @param string $columnName Name der Spalte
     * @param string|int $option Name/Id der Option
     * @param mixed $value Wert der Option
     * @return void
     */
    public function setColumnOption($columnName, $option, $value)
    {
        $this->columnOptions[$columnName][$option] = $value;
    }

    /**
     * Gibt den Wert einer Option für eine Spalte zurück.
     *
     * @param string $columnName Name der Spalte
     * @param string|int $option Name/Id der Option
     * @param mixed $default Defaultrückgabewert, falls die Option nicht gesetzt ist
     *
     * @return mixed|null
     */
    public function getColumnOption($columnName, $option, $default = null)
    {
        if ($this->hasColumnOption($columnName, $option)) {
            return $this->columnOptions[$columnName][$option];
        }
        return $default;
    }

    /**
     * Gibt zurück, ob für eine Spalte eine Option gesetzt wurde.
     *
     * @param string $columnName Name der Spalte
     * @param string|int $option Name/Id der Option
     *
     * @return bool
     */
    public function hasColumnOption($columnName, $option)
    {
        return isset($this->columnOptions[$columnName][$option]);
    }

    /**
     * Verlinkt eine Spalte mit den übergebenen Parametern.
     *
     * @param string $columnName Name der Spalte
     * @param array $params Array von Parametern
     * @return void
     */
    public function setColumnParams($columnName, array $params = [])
    {
        $this->columnParams[$columnName] = $params;
    }

    /**
     * Gibt die Parameter für eine Spalte zurück.
     *
     * @param string $columnName Name der Spalte
     *
     * @return array
     */
    public function getColumnParams($columnName)
    {
        if (isset($this->columnParams[$columnName]) && is_array($this->columnParams[$columnName])) {
            return $this->columnParams[$columnName];
        }
        return [];
    }

    /**
     * Gibt zurück, ob Parameter für eine Spalte existieren.
     *
     * @param string $columnName Name der Spalte
     *
     * @return bool
     */
    public function hasColumnParams($columnName)
    {
        return isset($this->columnParams[$columnName]) && is_array($this->columnParams[$columnName]) && count($this->columnParams[$columnName]) > 0;
    }

    /**
     * Verschiebt eine Spalte an eine andere Position in der Spaltenliste.
     *
     * @param string $columnName Name der Spalte
     * @param int|string $columnIndex Einfügen vor der angegebenen Spalte
     *                                (Spalten-Index analog zu addColumn oder Spaltenname)
     *
     * @return int Spaltennummer der neuen Position
     */
    public function setColumnPosition(string $columnName, $columnIndex): int
    {
        $currentIndex = $this->getColumnPosition($columnName);

        if (is_string($columnIndex)) {
            $columnIndex = $this->getColumnPosition($columnIndex);
        }

        // Bei negativem columnIndex das Element am Ende anfügen
        if (0 > $columnIndex) {
            $columnIndex = count($this->columnNames);
        }

        unset($this->columnNames[$currentIndex]);
        array_splice($this->columnNames, $columnIndex, 0, [$columnName]);

        return $columnIndex;
    }

    /**
     * Gibt die Position einer Spalte zurück.
     *
     * @param string $columnName Name der Spalte
     *
     * @throws InvalidArgumentException $columnName kommt in $this->columnNames nicht vor
     *
     * @return int Index der Spalte
     */
    public function getColumnPosition(string $columnName): int
    {
        $position = array_search($columnName, $this->columnNames);
        if (false === $position) {
            throw new InvalidArgumentException('Unkown column name "' . $columnName . '".');
        }
        return $position;
    }

    // ---------------------- TableColumnGroup setters/getters/etc

    /**
     * Methode um eine Colgroup einzufügen.
     *
     * Beispiel 1:
     *
     * $list->addTableColumnGroup([40, '*', 240, 140]);
     *
     * Beispiel 2:
     *
     * $list->addTableColumnGroup([
     *     ['width' => 40],
     *     ['width' => 140, 'span' => 2],
     *     ['width' => 240]
     * ]);
     *
     * Beispiel 3:
     *
     * $list->addTableColumnGroup([
     *     ['class' => 'classname-a'],
     *     ['class' => 'classname-b'],
     *     ['class' => 'classname-c']
     * ]);
     *
     * @param array $columns Array von Spalten
     * @param int $columnGroupSpan Span der Columngroup
     * @return void
     */
    public function addTableColumnGroup(array $columns, $columnGroupSpan = null)
    {
        $tableColumnGroup = ['columns' => []];
        if ($columnGroupSpan) {
            $tableColumnGroup['span'] = $columnGroupSpan;
        }
        $this->tableColumnGroups[] = $tableColumnGroup;

        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->addTableColumn($column['width'] ?? null, $column['span'] ?? null, $column['class'] ?? null);
            } else {
                $this->addTableColumn($column);
            }
        }
    }

    /** @return array<int, array> */
    public function getTableColumnGroups()
    {
        return $this->tableColumnGroups;
    }

    /**
     * Fügt der zuletzte eingefügten TableColumnGroup eine weitere Spalte hinzu.
     *
     * @param int|'*' $width Breite der Spalte
     * @param int $span Span der Spalte
     * @return void
     */
    public function addTableColumn($width, $span = null, $class = null)
    {
        $tableColumn = [];
        if (is_numeric($width)) {
            $width .= 'px';
        }
        if ($width && '*' != $width) {
            $tableColumn['style'] = 'width:' . $width;
        }
        if ($span) {
            $tableColumn['span'] = $span;
        }
        if ($class) {
            $tableColumn['class'] = $class;
        }

        $lastIndex = count($this->tableColumnGroups) - 1;

        if ($lastIndex < 0) {
            // Falls noch keine TableColumnGroup vorhanden, eine leere anlegen!
            $this->addTableColumnGroup([]);
            ++$lastIndex;
        }

        $groupColumns = $this->tableColumnGroups[$lastIndex]['columns'];
        $groupColumns[] = $tableColumn;
        $this->tableColumnGroups[$lastIndex]['columns'] = $groupColumns;
    }

    // ---------------------- Url generation

    /**
     * @param bool $escape
     * @return string
     */
    public function getUrl(array $params = [], $escape = true)
    {
        $params = array_merge($this->getParams(), $params);

        $params['list'] = $this->getName();

        if ($cursor = $this->pager?->getCursor()) {
            $params[$this->pager->getCursorName()] ??= $cursor;
        }

        if (!isset($params['sort'])) {
            $sortColumn = $this->getSortColumn();
            if (null != $sortColumn) {
                $params['sort'] = $sortColumn;
                $params['sorttype'] = $this->getSortType();
            }
        }

        $flatParams = [];
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $flatParams[$name] = $v;
                }
            } else {
                $flatParams[$name] = $value;
            }
        }

        return rex::isBackend() ? rex_url::backendController($flatParams, $escape) : rex_url::frontendController($flatParams, $escape);
    }

    /**
     * Gibt eine Url zurück, die die Parameter $params enthält
     * Dieser Url werden die Standard rexList Variablen zugefügt.
     *
     * Innerhalb dieser Url werden variablen ersetzt
     *
     * @see #replaceVariable, #replaceVariables
     *
     * @param array $params
     * @param bool $escape Flag whether the argument separator "&" should be escaped (&amp;)
     *
     * @return string
     */
    public function getParsedUrl($params = [], $escape = true)
    {
        $params = array_merge($this->getParams(), $params);

        $params['list'] = $this->getName();

        if ($cursor = $this->pager?->getCursor()) {
            $params[$this->pager->getCursorName()] ??= $cursor;
        }

        if (!isset($params['sort'])) {
            $sortColumn = $this->getSortColumn();
            if (null != $sortColumn) {
                $params['sort'] = $sortColumn;
                $params['sorttype'] = $this->getSortType();
            }
        }

        $flatParams = [];
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $flatParams[$name] = $this->replaceVariables($v);
                }
            } else {
                $flatParams[$name] = $this->replaceVariables((string) $value);
            }
        }
        return rex::isBackend() ? rex_url::backendController($flatParams, $escape) : rex_url::frontendController($flatParams, $escape);
    }

    // ---------------------- Pagination

    /**
     * Prepariert das SQL Statement vorm anzeigen der Liste.
     *
     * @param string $query SQL Statement
     * @param array<string, 'asc'|'desc'> $defaultSort
     *
     * @return string
     */
    protected function prepareQuery($query, array $defaultSort = [])
    {
        $sortColumn = $this->getSortColumn();
        if ('' != $sortColumn) {
            $sortType = $this->getSortType();

            $sql = rex_sql::factory($this->db);
            $sortColumn = $sql->escapeIdentifier($sortColumn);

            if ($defaultSort || false === stripos($query, ' ORDER BY ')) {
                $query .= ' ORDER BY ' . $sortColumn . ' ' . $sortType;
            }
        } elseif ($defaultSort) {
            $sort = [];

            $sql = rex_sql::factory($this->db);
            foreach ($defaultSort as $column => $type) {
                $type = strtolower($type);
                if (!in_array($type, ['asc', 'desc'], true)) {
                    throw new InvalidArgumentException('Default sort type must be "asc" or "desc", but "' . $type . '" given');
                }
                $sort[] = $sql->escapeIdentifier($column) . ' ' . $type;
            }

            $query .= ' ORDER BY ' . implode(', ', $sort);
        }

        if ($this->pager && false === stripos($query, ' LIMIT ')) {
            $query .= ' LIMIT ' . $this->pager->getCursor() . ',' . $this->pager->getRowsPerPage();
        }

        return $query;
    }

    private static function prepareCountQuery(string $query): string
    {
        return 'SELECT COUNT(*) AS `rows` FROM (' . $query . ') t';
    }

    /**
     * Gibt die Anzahl der Zeilen zurück, welche vom ursprüngliche SQL Statement betroffen werden.
     *
     * @return int
     */
    public function getRows()
    {
        return $this->rows;
    }

    protected function getRowsOnCurrentPage(): int
    {
        $nbRows = $this->getRows();

        if ($this->pager) {
            $maxRows = min($this->pager->getRowsPerPage(), $nbRows - $this->pager->getCursor());
        } else {
            $maxRows = $nbRows;
        }

        return $maxRows;
    }

    /**
     * Returns the pager for this list.
     *
     * @return rex_pager|null
     */
    public function getPager()
    {
        return $this->pager;
    }

    /**
     * Gibt zurück, nach welcher Spalte sortiert werden soll.
     *
     * @param string|null $default
     *
     * @return string|null
     */
    public function getSortColumn($default = null)
    {
        if (rex_request('list', 'string') == $this->getName()) {
            return rex_request('sort', 'string', $default);
        }
        return $default;
    }

    /**
     * Gibt zurück, in welcher Art und Weise sortiert werden soll (ASC/DESC).
     *
     * @param 'asc'|'desc'|null $default
     *
     * @return string|null
     *
     * @psalm-taint-escape html
     * @psalm-taint-escape sql
     */
    public function getSortType($default = null)
    {
        if (rex_request('list', 'string') == $this->getName()) {
            $sortType = strtolower(rex_request('sorttype', 'string'));

            if (in_array($sortType, ['asc', 'desc'], true)) {
                return $sortType;
            }
        }

        if (null === $default) {
            return null;
        }

        $default = strtolower($default);
        if (!in_array($default, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Default sort type must be "asc", "desc" or null, but "' . $default . '" given');
        }

        return $default;
    }

    /**
     * Gibt die Navigation der Liste zurück.
     *
     * @return string
     */
    protected function getPagination()
    {
        if (null === $this->pager) {
            return '';
        }

        $fragment = new rex_fragment();
        $fragment->setVar('urlprovider', $this);
        $fragment->setVar('pager', $this->pager);
        return $fragment->parse('core/navigations/pagination.php');
    }

    /**
     * Gibt den Footer der Liste zurück.
     *
     * @return string
     */
    public function getFooter()
    {
        $s = '';
        /*
        $s .= '            <tr>'. "\n";
        $s .= '                <td colspan="'. count($this->getColumnNames()) .'"><input type="text" name="items" value="'. $this->getRowsPerPage() .'" maxlength="2" /><input type="submit" value="Anzeigen" /></td>'. "\n";
        $s .= '            </tr>'. "\n";
        */
        return $s;
    }

    /**
     * Gibt den Header der Liste zurück.
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->getPagination();
    }

    // ---------------------- Generate Output

    /**
     * @param string $string
     * @param string $varname
     * @return string
     */
    public function replaceVariable($string, $varname)
    {
        return str_replace('###' . $varname . '###', rex_escape((string) $this->getValue($varname)), $string);
    }

    /**
     * Ersetzt alle Variablen im Format ###&lt;Spaltenname&gt;###.
     *
     * @param string $value Zu durchsuchender String
     *
     * @return string
     *
     * @psalm-taint-specialize
     */
    public function replaceVariables($value)
    {
        if (!str_contains($value, '###')) {
            return $value;
        }

        $columnNames = $this->getColumnNames();

        if (is_array($columnNames)) {
            foreach ($columnNames as $columnName) {
                // Spalten, die mit addColumn eingefügt wurden
                if (isset($this->customColumns[$columnName])) {
                    continue;
                }

                $value = $this->replaceVariable($value, $columnName);
            }
        }
        return $value;
    }

    /** @return bool */
    public function isCustomFormat($format)
    {
        return is_array($format) && isset($format[0]) && 'custom' == $format[0];
    }

    /**
     * Formatiert einen übergebenen String anhand der rexFormatter Klasse.
     *
     * @param string $value Zu formatierender String
     * @param array|null $format mit den Formatierungsinformationen
     * @param bool $escape Flag, Ob escapen von $value erlaubt ist
     * @param string $field
     *
     * @return string
     */
    public function formatValue($value, $format, $escape, $field = null)
    {
        if (is_array($format)) {
            // Callbackfunktion -> Parameterliste aufbauen
            if ($this->isCustomFormat($format)) {
                $format[2] ??= [];
                $format[1] = [$format[1], ['list' => $this, 'field' => $field, 'value' => $value, 'format' => $format[0], 'escape' => $escape, 'params' => $format[2]]];
            }

            $value = rex_formatter::format($value, $format[0], $format[1]);
        }

        // Nur escapen, wenn formatter aufgerufen wird, der kein html zurückgeben können soll
        if ($escape && (!isset($format[0]) || !in_array($format[0], ['custom', 'email', 'url'], true))) {
            $value = rex_escape($value);
        }

        return $value;
    }

    protected function formatRowAttributes(): string
    {
        $rowAttributesCallable = null;
        if (is_callable($this->rowAttributes)) {
            $rowAttributesCallable = $this->rowAttributes;
        } elseif ($this->rowAttributes) {
            $rowAttributes = rex_string::buildAttributes($this->rowAttributes);
            $rowAttributesCallable = function (self $list) use ($rowAttributes) {
                return $this->replaceVariables($rowAttributes);
            };
        }

        if ($rowAttributesCallable) {
            return ' ' . $rowAttributesCallable($this);
        }

        return '';
    }

    /** @return string */
    protected function _getAttributeString($array)
    {
        $s = '';

        foreach ($array as $name => $value) {
            $s .= ' ' . rex_escape($name, 'html_attr') . '="' . rex_escape($value) . '"';
        }

        return $s;
    }

    /** @return string */
    public function getColumnLink($columnName, $columnValue, $params = [])
    {
        $attributes = $this->getLinkAttributes($columnName, []);
        if (!isset($attributes['class']) && rex::isBackend()) {
            $attributes['class'] = 'rex-link-expanded';
        }
        return '<a href="' . $this->getParsedUrl(array_merge($this->getColumnParams($columnName), $params)) . '"' . $this->_getAttributeString($attributes) . '>' . $columnValue . '</a>';
    }

    /**
     * @param string $column
     * @return scalar|null
     */
    public function getValue($column)
    {
        return $this->customColumns[$column] ?? $this->sql->getValue($column);
    }

    /**
     * @param string $column
     * @return array
     */
    public function getArrayValue($column)
    {
        return json_decode($this->getValue($column), true);
    }

    /**
     * Erstellt den Tabellen Quellcode.
     *
     * @return string
     */
    public function get()
    {
        rex_extension::registerPoint(new rex_extension_point('REX_LIST_GET', $this, [], true));

        $s = "\n";

        // Form vars
        $this->addFormAttribute('action', $this->getUrl([], false));
        $this->addFormAttribute('method', 'post');

        // Table vars
        $caption = $this->getCaption();
        $tableColumnGroups = $this->getTableColumnGroups();
        $class = 'table';
        if (isset($this->tableAttributes['class'])) {
            $class .= ' ' . $this->tableAttributes['class'];
        }
        $this->addTableAttribute('class', $class);

        // Columns vars
        $columnFormates = [];
        $columnNames = $this->getEnabledColumnNames();

        // List vars
        $sortColumn = $this->getSortColumn();
        $sortType = $this->getSortType();
        $warning = $this->getWarning();
        $message = $this->getMessage();

        $header = $this->getHeader();
        $footer = $this->getFooter();

        if ('' != $warning) {
            $s .= rex_view::warning($warning) . "\n";
        } elseif ('' != $message) {
            $s .= rex_view::info($message) . "\n";
        }

        if ('' != $header) {
            $s .= $header . "\n";
        }

        $s .= '<form' . $this->_getAttributeString($this->getFormAttributes()) . '>' . "\n";
        $s .= '    <table' . $this->_getAttributeString($this->getTableAttributes()) . '>' . "\n";

        if ('' != $caption) {
            $s .= '        <caption>' . rex_escape($caption) . '</caption>' . "\n";
        }

        foreach ($tableColumnGroups as $tableColumnGroup) {
            $tableColumns = $tableColumnGroup['columns'];
            unset($tableColumnGroup['columns']);

            $s .= '        <colgroup' . $this->_getAttributeString($tableColumnGroup) . '>' . "\n";

            foreach ($tableColumns as $tableColumn) {
                $s .= '            <col' . $this->_getAttributeString($tableColumn) . ' />' . "\n";
            }

            $s .= '        </colgroup>' . "\n";
        }

        $s .= '        <thead>' . "\n";
        $s .= '            <tr>' . "\n";
        foreach ($columnNames as $columnName) {
            $columnHead = $this->getColumnLabel($columnName);
            if ($this->hasColumnOption($columnName, REX_LIST_OPT_SORT)) {
                if ($columnName == $sortColumn) {
                    $columnSortType = 'desc' == $sortType ? 'asc' : 'desc';
                } else {
                    $columnSortType = $this->getColumnOption($columnName, REX_LIST_OPT_SORT_DIRECTION, 'asc');
                }
                $params = $this->pager ? [$this->pager->getCursorName() => $this->pager->getCursor()] : [];
                $params = array_merge($params, ['sort' => $columnName, 'sorttype' => $columnSortType]);
                $columnHead = '<a class="rex-link-expanded" href="' . $this->getUrl($params) . '">' . $columnHead . '</a>';
            }

            $layout = $this->getColumnLayout($columnName);
            $s .= '        ' . str_replace('###VALUE###', $columnHead, $layout[0]) . "\n";

            // Formatierungen hier holen, da diese Schleife jede Spalte nur einmal durchläuft
            $columnFormates[$columnName] = $this->getColumnFormat($columnName);
        }
        $s .= '            </tr>' . "\n";
        $s .= '        </thead>' . "\n";

        if ('' != $footer) {
            $s .= '        <tfoot>' . "\n";
            $s .= $footer;
            $s .= '        </tfoot>' . "\n";
        }

        if ($this->getRows() > 0) {
            $maxRows = $this->getRowsOnCurrentPage();

            $s .= '        <tbody>' . "\n";
            for ($i = 0; $i < $maxRows; ++$i) {
                $rowAttributes = $this->formatRowAttributes();

                $s .= '            <tr' . $rowAttributes . ">\n";
                foreach ($columnNames as $columnName) {
                    $columnFormat = $columnFormates[$columnName];
                    $columnValue = $this->getColumnValue($columnName, $columnFormat);

                    if (!$this->isCustomFormat($columnFormat) && $this->hasColumnParams($columnName)) {
                        $columnValue = $this->getColumnLink($columnName, $columnValue);
                    }

                    $columnHead = $this->getColumnLabel($columnName);
                    $layout = $this->getColumnLayout($columnName);
                    $columnValue = str_replace(['###VALUE###', '###LABEL###'], [$columnValue, $columnHead], $layout[1]);
                    $columnValue = $this->replaceVariables($columnValue);
                    $s .= '        ' . $columnValue . "\n";
                }
                $s .= '            </tr>' . "\n";

                $this->sql->next();
            }
            $s .= '        </tbody>' . "\n";
        } else {
            $s .= '<tr class="table-no-results"><td colspan="' . count($columnNames) . '">' . $this->getNoRowsMessage() . '</td></tr>';
        }

        $s .= '    </table>' . "\n";
        $s .= '</form>' . "\n";

        return $s;
    }

    /** @return void */
    public function show()
    {
        echo $this->get();
    }
}
