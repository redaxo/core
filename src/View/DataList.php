<?php

namespace Redaxo\Core\View;

use Override;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Base\UrlProviderInterface;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Str;

use function count;
use function define;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;

// Nötige Konstanten
define('REX_LIST_OPT_SORT', 0);
define('REX_LIST_OPT_SORT_DIRECTION', 1);

/*
EXAMPLE:

$list = List::factory('SELECT id,name FROM rex_article');
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
    // $params['list']     List object
    // $params['params']   custom params

    return $custom_string; // return value showed in list (note: no htmlspechialchars!)
}

// USING setColumnFormat() BY CALLING A FUNCTION
$list->setColumnFormat(
    'id',                                    // field name
    'custom',                                // format type
    'callback_func',                         // callback function name
    array('foo' => 'bar', '123' => '456')    // optional params for callback function
);

// USING setColumnFormat() BY CALLING CLASS & METHOD
$list->setColumnFormat(
    'id',                                    // field name
    'custom',                                // format type
    array('CLASS','METHOD'),                 // callback class/method name
    array('foo' => 'bar', '123' => '456')    // optional params for callback function
);
*/

/**
 * Klasse zum erstellen von Listen.
 *
 * @psalm-consistent-constructor
 */
class DataList implements UrlProviderInterface
{
    use FactoryTrait;

    public const DISABLE_PAGINATION = null;

    protected Sql $sql;
    private string $noRowsMessage;

    // --------- List Attributes
    private readonly string $name;
    /** @var array<string, string|int> */
    private array $params = [];
    private int $rows = 0;

    // --------- Form Attributes
    /** @var array<string, string|int> */
    private array $formAttributes = [];

    //  --------- Row Attributes
    /** @var array<string, string|int>|callable(self):string */
    private $rowAttributes = [];

    // --------- Column Attributes
    /** @var array<string, string> */
    private array $customColumns = [];
    /** @var list<string> */
    private array $columnNames = [];
    /** @var array<string, string> */
    private array $columnLabels = [];
    /** @var array<string, array{string, mixed, array<mixed>}> */
    private array $columnFormates = [];
    /** @var array<string, array<string|int, mixed>> */
    private array $columnOptions = [];
    /** @var array<string, array{string, string}> */
    private array $columnLayouts = [];
    /** @var array<string, array> */
    private array $columnParams = [];
    /** @var list<string> */
    private array $columnDisabled = [];

    // --------- Layout, Default
    /** @var array{string, string} */
    private array $defaultColumnLayout = ['<th>###VALUE###</th>', '<td data-title="###LABEL###">###VALUE###</td>'];

    // --------- Table Attributes
    private string $caption = '';
    /** @var array<string, string|int> */
    private array $tableAttributes = [];
    /** @var array<int, array> */
    private array $tableColumnGroups = [];

    // --------- Link Attributes
    /** @var array<string, array<string, string|int>> */
    private array $linkAttributes = [];

    // --------- Pagination Attributes
    private ?Pager $pager = null;

    /**
     * @param string $query SELECT Statement
     * @param int|self::DISABLE_PAGINATION $rowsPerPage
     * @param string|null $listName Name der Liste
     * @param positive-int $db
     * @param array<string, 'asc'|'desc'> $defaultSort
     */
    protected function __construct(
        string $query,
        ?int $rowsPerPage = 30,
        ?string $listName = null,
        private readonly bool $debug = false,
        private readonly int $db = 1,
        array $defaultSort = [],
    ) {
        // --------- Validation
        if (!$listName) {
            // use a hopefully unique (per page) hash
            $listName = substr(md5($query), 0, 8);
        }

        // --------- List Attributes
        $this->sql = Sql::factory($db);
        $this->sql->setDebug($this->debug);
        $this->name = $listName;
        $this->noRowsMessage = I18n::msg('list_no_rows');

        // --------- Pagination Attributes
        if (self::DISABLE_PAGINATION !== $rowsPerPage) {
            $cursorName = $listName . '_start';
            if (null === Request::request($cursorName, 'int', null) && Request::request('start', 'int')) {
                // BC: Fallback to "start"
                $cursorName = 'start';
            }
            $this->pager = new Pager($rowsPerPage, $cursorName);

            $sql = Sql::factory($db);
            $sql->setQuery(self::prepareCountQuery($query));
            $this->rows = (int) $sql->getValue('rows');
            $this->pager->setRowCount($this->rows);
        }

        // --------- Load Data
        $this->sql->setQuery($this->prepareQuery($query, $defaultSort));
        if (self::DISABLE_PAGINATION === $rowsPerPage) {
            $this->rows = $this->sql->getRows();
        }

        foreach ($this->sql->getFieldnames() as $columnName) {
            $this->columnNames[] = $columnName;
        }

        // --------- Load Env
        if (Core::isBackend()) {
            $this->loadBackendConfig();
        }

        $this->init();
    }

    /**
     * @param int|self::DISABLE_PAGINATION $rowsPerPage
     * @param positive-int $db DB connection ID
     * @param array<string, 'asc'|'desc'> $defaultSort
     */
    public static function factory(string $query, ?int $rowsPerPage = 30, ?string $listName = null, bool $debug = false, int $db = 1, array $defaultSort = []): static
    {
        $class = static::getFactoryClass();
        return new $class($query, $rowsPerPage, $listName, $debug, $db, $defaultSort);
    }

    public function init(): void
    {
        // nichts tun
    }

    // ---------------------- setters/getters

    /** Gibt den Namen es Formulars zurück. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Gibt eine Status Nachricht zurück. */
    public function getMessage(): string
    {
        return escape(Request::request($this->getName() . '_msg', 'string'));
    }

    /** Gibt eine Warnung zurück. */
    public function getWarning(): string
    {
        return escape(Request::request($this->getName() . '_warning', 'string'));
    }

    /** Setzt die Caption/den Titel der Tabelle. */
    public function setCaption(string $caption): void
    {
        $this->caption = $caption;
    }

    /** Gibt die Caption/den Titel der Tabelle zurück. */
    public function getCaption(): string
    {
        return $this->caption;
    }

    public function setNoRowsMessage(string $message): void
    {
        $this->noRowsMessage = $message;
    }

    public function getNoRowsMessage(): string
    {
        return $this->noRowsMessage;
    }

    public function addParam(string $name, string|int $value): void
    {
        $this->params[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getParams(): array
    {
        return $this->params;
    }

    protected function loadBackendConfig(): void
    {
        $this->addParam('page', Controller::getCurrentPage());
    }

    public function addTableAttribute(string $name, string|int $value): void
    {
        $this->tableAttributes[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getTableAttributes(): array
    {
        return $this->tableAttributes;
    }

    public function addFormAttribute(string $name, string|int $value): void
    {
        $this->formAttributes[$name] = $value;
    }

    /** @return array<string, string|int> */
    public function getFormAttributes(): array
    {
        return $this->formAttributes;
    }

    public function addLinkAttribute(string $columnName, string $attrName, string|int $attrValue): void
    {
        $this->linkAttributes[$columnName][$attrName] = $attrValue;
    }

    /**
     * @template TDefault of array<string, string|int>|null
     * @param TDefault $default
     * @return array<string, string|int>|TDefault
     */
    public function getLinkAttributes(string $column, ?array $default = null): ?array
    {
        return $this->linkAttributes[$column] ?? $default;
    }

    /**
     * Methode, um der Zeile (<tr>) Attribute hinzuzufügen.
     *
     * @param array<string, string|int>|callable(self):string $attr Entweder ein array: [attributname => attribut, ...]
     *                                                              oder eine Callback-Funktion
     */
    public function setRowAttributes(array|callable $attr): void
    {
        $this->rowAttributes = $attr;
    }

    /**
     * Methode, um die Zeilen-Attribute (<tr>) abzufragen.
     *
     * @return array<string, string|int>|callable(self):string Entweder ein array: [attributname => attribut, ...]
     *                                                         oder eine Callback-Funktion
     */
    public function getRowAttributes(): array|callable
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
     * @param array{string, string} $columnLayout Layout der Spalte
     */
    public function addColumn(string $columnHead, string $columnBody, int $columnIndex = -1, ?array $columnLayout = null): void
    {
        // Bei negativem columnIndex, das Element am Ende anfügen
        if ($columnIndex < 0) {
            $columnIndex = count($this->columnNames);
        }

        array_splice($this->columnNames, $columnIndex, 0, [$columnHead]);
        $this->customColumns[$columnHead] = $columnBody;
        $this->setColumnLayout($columnHead, $columnLayout);
    }

    /** Entfernt eine Spalte aus der Anzeige. */
    public function removeColumn(string $columnName): void
    {
        $this->columnDisabled[] = $columnName;
    }

    /**
     * Methode, um das Layout einer Spalte zu setzen.
     *
     * @param array{string, string} $columnLayout Layout der Spalte
     */
    public function setColumnLayout(string $columnName, array $columnLayout): void
    {
        $this->columnLayouts[$columnName] = $columnLayout;
    }

    /**
     * Gibt das Layout einer Spalte zurück.
     *
     * @return array{string, string}
     */
    public function getColumnLayout(string $columnName): array
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
    public function getColumnLayouts(): array
    {
        return $this->columnLayouts;
    }

    /**
     * Gibt den Namen einer Spalte zurück.
     *
     * @param string|null $default Defaultrückgabewert, falls keine Spalte mit der angegebenen Nummer vorhanden ist
     */
    public function getColumnName(int $columnIndex, ?string $default = null): ?string
    {
        return $this->columnNames[$columnIndex] ?? $default;
    }

    /**
     * Gibt alle Namen der Spalten als Array zurück.
     *
     * @return list<string>
     */
    public function getColumnNames(): array
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

    /** @param array{string, mixed, array<mixed>}|null $columnFormat */
    protected function getColumnValue(string $columnName, ?array $columnFormat): string
    {
        return $this->formatValue(
            $this->getValue($columnName),
            $columnFormat,
            !isset($this->customColumns[$columnName]),
            $columnName,
        );
    }

    /** Setzt ein Label für eine Spalte. */
    public function setColumnLabel(string $columnName, string $label): void
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
     * @param T $default Defaultrückgabewert, falls kein Label gesetzt ist
     * @return (T is null ? string : ?string)
     */
    public function getColumnLabel(string $columnName, ?string $default = null): ?string
    {
        return $this->columnLabels[$columnName] ?? $default ?? $columnName;
    }

    /**
     * Setzt ein Format für die Spalte.
     *
     * @param string $formatType Formatierungstyp
     * @param mixed $format Zu verwendentes Format
     * @param array $params Custom params für callback func bei format_type 'custom'
     */
    public function setColumnFormat(string $columnName, string $formatType, mixed $format = null, array $params = []): void
    {
        $this->columnFormates[$columnName] = [$formatType, $format, $params];
    }

    /**
     * Gibt das Format für eine Spalte zurück.
     *
     * @template T of array{string, mixed, array<mixed>}|null
     *
     * @param T $default Defaultrückgabewert, falls keine Formatierung gesetzt ist
     *
     * @return array{string, mixed, array<mixed>}|T
     */
    public function getColumnFormat(string $columnName, ?array $default = null): ?array
    {
        return $this->columnFormates[$columnName] ?? $default;
    }

    /**
     * Markiert eine Spalte als sortierbar.
     *
     * @param string $direction Startsortierrichtung der Spalte [ASC|DESC]
     */
    public function setColumnSortable(string $columnName, string $direction = 'asc'): void
    {
        $this->setColumnOption($columnName, REX_LIST_OPT_SORT, true);
        $this->setColumnOption($columnName, REX_LIST_OPT_SORT_DIRECTION, strtolower($direction));
    }

    /**
     * Setzt eine Option für eine Spalte
     * (z.b. Sortable,..).
     *
     * @param string|int $option Name/Id der Option
     */
    public function setColumnOption(string $columnName, string|int $option, mixed $value): void
    {
        $this->columnOptions[$columnName][$option] = $value;
    }

    /**
     * Gibt den Wert einer Option für eine Spalte zurück.
     *
     * @param string|int $option Name/Id der Option
     * @param mixed $default Defaultrückgabewert, falls die Option nicht gesetzt ist
     */
    public function getColumnOption(string $columnName, string|int $option, mixed $default = null): mixed
    {
        if ($this->hasColumnOption($columnName, $option)) {
            return $this->columnOptions[$columnName][$option];
        }
        return $default;
    }

    /**
     * Gibt zurück, ob für eine Spalte eine Option gesetzt wurde.
     *
     * @param string|int $option Name/Id der Option
     */
    public function hasColumnOption(string $columnName, string|int $option): bool
    {
        return isset($this->columnOptions[$columnName][$option]);
    }

    /**
     * Verlinkt eine Spalte mit den übergebenen Parametern.
     *
     * @param array $params Array von Parametern
     */
    public function setColumnParams(string $columnName, array $params = []): void
    {
        $this->columnParams[$columnName] = $params;
    }

    /** Gibt die Parameter für eine Spalte zurück. */
    public function getColumnParams(string $columnName): array
    {
        if (isset($this->columnParams[$columnName]) && is_array($this->columnParams[$columnName])) {
            return $this->columnParams[$columnName];
        }
        return [];
    }

    /** Gibt zurück, ob Parameter für eine Spalte existieren. */
    public function hasColumnParams(string $columnName): bool
    {
        return isset($this->columnParams[$columnName]) && is_array($this->columnParams[$columnName]) && count($this->columnParams[$columnName]) > 0;
    }

    /**
     * Verschiebt eine Spalte an eine andere Position in der Spaltenliste.
     *
     * @param int|string $columnIndex Einfügen vor der angegebenen Spalte
     *                                (Spalten-Index analog zu addColumn oder Spaltenname)
     *
     * @return int Spaltennummer der neuen Position
     */
    public function setColumnPosition(string $columnName, int|string $columnIndex): int
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
     * @param int|null $columnGroupSpan Span der Columngroup
     */
    public function addTableColumnGroup(array $columns, ?int $columnGroupSpan = null): void
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
    public function getTableColumnGroups(): array
    {
        return $this->tableColumnGroups;
    }

    /**
     * Fügt der zuletzte eingefügten TableColumnGroup eine weitere Spalte hinzu.
     *
     * @param int|'*' $width Breite der Spalte
     * @param int|null $span Span der Spalte
     */
    public function addTableColumn(int|string $width, ?int $span = null, ?int $class = null): void
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

    #[Override]
    public function getUrl(array $params = []): string
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

        return Core::isBackend() ? Url::backendController($flatParams) : Url::frontendController($flatParams);
    }

    /**
     * Gibt eine Url zurück, die die Parameter $params enthält
     * Dieser Url werden die Standard rexList Variablen zugefügt.
     *
     * Innerhalb dieser Url werden variablen ersetzt
     *
     * @see #replaceVariable, #replaceVariables
     */
    public function getParsedUrl(array $params = []): string
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
        return Core::isBackend() ? Url::backendController($flatParams) : Url::frontendController($flatParams);
    }

    // ---------------------- Pagination

    /**
     * Prepariert das SQL Statement vorm anzeigen der Liste.
     *
     * @param string $query SQL Statement
     * @param array<string, 'asc'|'desc'> $defaultSort
     */
    protected function prepareQuery(string $query, array $defaultSort = []): string
    {
        $sortColumn = $this->getSortColumn();
        if ('' != $sortColumn) {
            $sortType = $this->getSortType();

            $sql = Sql::factory($this->db);
            $sortColumn = $sql->escapeIdentifier($sortColumn);

            if ($defaultSort || false === stripos($query, ' ORDER BY ')) {
                $query .= ' ORDER BY ' . $sortColumn . ' ' . $sortType;
            }
        } elseif ($defaultSort) {
            $sort = [];

            $sql = Sql::factory($this->db);
            foreach ($defaultSort as $column => $type) {
                $type = strtolower($type);
                if (!in_array($type, ['asc', 'desc'], true)) {
                    throw new InvalidArgumentException('Default sort type must be "asc" or "desc", but "' . $type . '" given.');
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

    /** Gibt die Anzahl der Zeilen zurück, welche vom ursprüngliche SQL Statement betroffen werden. */
    public function getRows(): int
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

    /** Returns the pager for this list. */
    public function getPager(): ?Pager
    {
        return $this->pager;
    }

    /** Gibt zurück, nach welcher Spalte sortiert werden soll. */
    public function getSortColumn(?string $default = null): ?string
    {
        if (Request::request('list', 'string') == $this->getName()) {
            return Request::request('sort', 'string', $default);
        }
        return $default;
    }

    /**
     * Gibt zurück, in welcher Art und Weise sortiert werden soll (ASC/DESC).
     *
     * @param 'asc'|'desc'|null $default
     *
     * @psalm-taint-escape html
     * @psalm-taint-escape sql
     */
    public function getSortType(?string $default = null): ?string
    {
        if (Request::request('list', 'string') == $this->getName()) {
            $sortType = strtolower(Request::request('sorttype', 'string'));

            if (in_array($sortType, ['asc', 'desc'], true)) {
                return $sortType;
            }
        }

        if (null === $default) {
            return null;
        }

        $default = strtolower($default);
        if (!in_array($default, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Default sort type must be "asc", "desc" or null, but "' . $default . '" given.');
        }

        return $default;
    }

    /** Gibt die Navigation der Liste zurück. */
    protected function getPagination(): string
    {
        if (null === $this->pager) {
            return '';
        }

        $fragment = new Fragment();
        $fragment->setVar('urlprovider', $this);
        $fragment->setVar('pager', $this->pager);
        return $fragment->parse('core/navigations/pagination.php');
    }

    /** Gibt den Footer der Liste zurück. */
    public function getFooter(): string
    {
        $s = '';
        /*
        $s .= '            <tr>'. "\n";
        $s .= '                <td colspan="'. count($this->getColumnNames()) .'"><input type="text" name="items" value="'. $this->getRowsPerPage() .'" maxlength="2" /><input type="submit" value="Anzeigen" /></td>'. "\n";
        $s .= '            </tr>'. "\n";
        */
        return $s;
    }

    /** Gibt den Header der Liste zurück. */
    public function getHeader(): string
    {
        return $this->getPagination();
    }

    // ---------------------- Generate Output

    public function replaceVariable(string $string, string $varname): string
    {
        return str_replace('###' . $varname . '###', escape((string) $this->getValue($varname)), $string);
    }

    /**
     * Ersetzt alle Variablen im Format ###&lt;Spaltenname&gt;###.
     *
     * @param string $value Zu durchsuchender String
     *
     * @psalm-taint-specialize
     */
    public function replaceVariables(string $value): string
    {
        if (!str_contains($value, '###')) {
            return $value;
        }

        foreach ($this->getColumnNames() as $columnName) {
            // Spalten, die mit addColumn eingefügt wurden
            if (isset($this->customColumns[$columnName])) {
                continue;
            }

            $value = $this->replaceVariable($value, $columnName);
        }

        return $value;
    }

    public function isCustomFormat(?array $format): bool
    {
        return is_array($format) && isset($format[0]) && 'custom' == $format[0];
    }

    /**
     * Formatiert einen übergebenen String anhand der rexFormatter Klasse.
     *
     * @param string $value Zu formatierender String
     * @param array|null $format mit den Formatierungsinformationen
     * @param bool $escape Flag, Ob escapen von $value erlaubt ist
     */
    public function formatValue(string $value, ?array $format, bool $escape, ?string $field = null): string
    {
        if (is_array($format)) {
            // Callbackfunktion -> Parameterliste aufbauen
            if ($this->isCustomFormat($format)) {
                $format[2] ??= [];
                $format[1] = [$format[1], ['list' => $this, 'field' => $field, 'value' => $value, 'format' => $format[0], 'escape' => $escape, 'params' => $format[2]]];
            }

            $value = Formatter::format($value, $format[0], $format[1]);
        }

        // Nur escapen, wenn formatter aufgerufen wird, der kein html zurückgeben können soll
        if ($escape && (!isset($format[0]) || !in_array($format[0], ['custom', 'email', 'url'], true))) {
            $value = escape($value);
        }

        return $value;
    }

    protected function formatRowAttributes(): string
    {
        $rowAttributesCallable = null;
        if (is_callable($this->rowAttributes)) {
            $rowAttributesCallable = $this->rowAttributes;
        } elseif ($this->rowAttributes) {
            $rowAttributes = Str::buildAttributes($this->rowAttributes);
            $rowAttributesCallable = function (self $list) use ($rowAttributes) {
                return $this->replaceVariables($rowAttributes);
            };
        }

        if ($rowAttributesCallable) {
            return ' ' . $rowAttributesCallable($this);
        }

        return '';
    }

    /** @param array<string, string|int> $array */
    protected function _getAttributeString(array $array): string
    {
        $s = '';

        foreach ($array as $name => $value) {
            $s .= ' ' . escape($name, 'html_attr') . '="' . escape($value) . '"';
        }

        return $s;
    }

    public function getColumnLink(string $columnName, string|int|float|bool|null $columnValue, array $params = []): string
    {
        $attributes = $this->getLinkAttributes($columnName, []);
        if (!isset($attributes['class']) && Core::isBackend()) {
            $attributes['class'] = 'rex-link-expanded';
        }
        return '<a href="' . $this->getParsedUrl(array_merge($this->getColumnParams($columnName), $params)) . '"' . $this->_getAttributeString($attributes) . '>' . ((string) $columnValue) . '</a>';
    }

    public function getValue(string $column): string|int|float|bool|null
    {
        return $this->customColumns[$column] ?? $this->sql->getValue($column);
    }

    public function getArrayValue(string $column): array
    {
        return json_decode($this->getValue($column), true);
    }

    /** Erstellt den Tabellen Quellcode. */
    public function get(): string
    {
        Extension::dispatch(new ExtensionPoint('REX_LIST_GET', $this, [], true));

        $s = "\n";

        // Form vars
        $this->addFormAttribute('action', $this->getUrl());
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
            $s .= Message::warning($warning) . "\n";
        } elseif ('' != $message) {
            $s .= Message::info($message) . "\n";
        }

        if ('' != $header) {
            $s .= $header . "\n";
        }

        $s .= '<form' . $this->_getAttributeString($this->getFormAttributes()) . '>' . "\n";
        $s .= '    <table' . $this->_getAttributeString($this->getTableAttributes()) . '>' . "\n";

        if ('' != $caption) {
            $s .= '        <caption>' . escape($caption) . '</caption>' . "\n";
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

    public function show(): void
    {
        echo $this->get();
    }
}
