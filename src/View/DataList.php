<?php

namespace Redaxo\Core\View;

use Closure;
use Override;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Base\UrlProviderInterface;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\Component\Pagination;
use Redaxo\Core\View\Component\Table;
use Redaxo\Core\View\Component\TableCell;
use Redaxo\Core\View\Component\TableColumn;

use function in_array;
use function is_bool;

/**
 * Table of the rows of an SQL query, paginated and sortable by the columns marked as such.
 *
 * ```php
 * $list = new DataList('SELECT id, name FROM rex_user_role ORDER BY name');
 * $list->column('id', I18n::msg('id'), type: ColumnType::Id);
 * $list->column('name', I18n::msg('name'), sortable: true, link: fn (array $row) => $list->getUrl(['id' => $row['id']]));
 * echo $list->render();
 * ```
 *
 * The cells receive the row as array of the query's fields.
 */
final class DataList implements Renderable, UrlProviderInterface
{
    public const int DISABLE_PAGINATION = 0;

    public readonly string $name;
    public readonly ?Pager $pager;

    public string|Renderable $caption = '';
    /** Shown instead of the rows when the query has none */
    public string|Renderable $empty;
    /** @var (Closure(array<string, scalar|null>): HtmlAttributes)|null attributes of a row, e.g. a state class */
    public ?Closure $rowAttributes = null;
    /** Attributes of the table, e.g. `table-hover` */
    public HtmlAttributes $attributes;

    /** @var array<string, string|int> */
    private array $params = [];
    /** @var array<string, array{TableColumn, 'asc'|'desc'|null}> */
    private array $columns = [];

    /**
     * @param string|null $name Distinguishes the request params of several lists on one page, defaults to a hash of the query
     * @param positive-int|self::DISABLE_PAGINATION|null $rowsPerPage Defaults to {@see Pager::$defaultRowsPerPage}
     * @param array<string, 'asc'|'desc'> $defaultSort Order while the list is not sorted by a column
     * @param positive-int $db
     */
    public function __construct(
        private readonly string $query,
        ?string $name = null,
        ?int $rowsPerPage = null,
        private readonly array $defaultSort = [],
        private readonly int $db = 1,
    ) {
        foreach ($defaultSort as $type) {
            // the direction goes into the query as is
            if (!in_array($type, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Default sort type must be "asc" or "desc", but "' . $type . '" given.');
            }
        }

        $this->name = $name ?? substr(md5($query), 0, 8);
        $this->empty = I18n::msg('list_no_rows');
        $this->attributes = new HtmlAttributes();

        if (self::DISABLE_PAGINATION === $rowsPerPage) {
            $this->pager = null;
        } else {
            $this->pager = new Pager($rowsPerPage, $this->name . '_start');
            $sql = Sql::factory($db);
            $sql->setQuery(self::prepareCountQuery($query));
            $this->pager->setRowCount($sql->getIntValue('rows'));
        }

        if (Core::isBackend()) {
            $this->params['page'] = Controller::getCurrentPage();
        }
    }

    /** Adds a param to all urls of the list. */
    public function addParam(string $name, string|int $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Adds a column. `$name` is the field of the query it shows and sorts by, or any name for a computed column.
     *
     * @param string|Renderable|null $label `null` joins the header of the previous column, e.g. for several actions
     * @param (Closure(array<string, scalar|null>): (string|int|float|Renderable|TableCell|null))|null $cell Defaults to the field `$name`
     * @param bool|'asc'|'desc' $sortable Direction of the first click on the header, `true` for ascending
     * @param (Closure(array<string, scalar|null>): ?string)|null $link Url the cell links to, `null` for a row without link
     * @param array<literal-string, string|bool> $linkAttributes
     */
    public function column(
        string $name,
        string|Renderable|null $label = '',
        ?Closure $cell = null,
        ColumnType $type = ColumnType::Text,
        bool|string $sortable = false,
        ?Closure $link = null,
        array $linkAttributes = [],
        HtmlAttributes $attributes = new HtmlAttributes(),
    ): void {
        $cell ??= static function (array $row) use ($name): string|int|float|null {
            /** @var scalar|null $value */
            $value = $row[$name] ?? null;
            return is_bool($value) ? (int) $value : $value;
        };

        if (true === $sortable) {
            $sortable = 'asc';
        }
        if (false !== $sortable && !in_array($sortable, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Sort direction must be "asc" or "desc", but "' . $sortable . '" given.');
        }

        $this->columns[$name] = [
            new TableColumn($label, $cell, $type, $link, $linkAttributes, attributes: $attributes),
            false === $sortable ? null : $sortable,
        ];
    }

    #[Override]
    public function getUrl(array $params = []): string
    {
        $params = array_merge($this->params, $params);
        $params['list'] = $this->name;

        if ($cursor = $this->pager?->getCursor()) {
            $params[$this->pager->getCursorName()] ??= $cursor;
        }

        if (!isset($params['sort']) && null !== $sortColumn = $this->getSortColumn()) {
            $params['sort'] = $sortColumn;
            $params['sorttype'] = $this->getSortType() ?? 'asc';
        }

        return Core::isBackend() ? Url::backendController($params) : Url::frontendController($params);
    }

    /**
     * Column the list is sorted by on request, only one of the sortable columns.
     *
     * @internal
     */
    public function getSortColumn(): ?string
    {
        if (Request::request('list', 'string') !== $this->name) {
            return null;
        }

        $column = Request::request('sort', 'string');
        return null !== ($this->columns[$column][1] ?? null) ? $column : null;
    }

    /**
     * @return 'asc'|'desc'|null
     *
     * @internal
     *
     * @psalm-taint-escape html
     * @psalm-taint-escape sql
     */
    public function getSortType(): ?string
    {
        if (null === $this->getSortColumn()) {
            return null;
        }

        $type = strtolower(Request::request('sorttype', 'string'));
        return in_array($type, ['asc', 'desc'], true) ? $type : 'asc';
    }

    /**
     * Rows of the current page.
     *
     * @return list<array<string, scalar|null>>
     */
    public function getRows(): array
    {
        return Sql::factory($this->db)->getArray($this->prepareQuery());
    }

    #[Override]
    public function render(): Html
    {
        $sortColumn = $this->getSortColumn();
        $sortType = $this->getSortType();

        $columns = [];
        foreach ($this->columns as $name => [$column, $sortable]) {
            if (null !== $sortable) {
                $column = clone $column;
                $column->sortUrl = $this->getUrl([
                    'sort' => $name,
                    'sorttype' => $name === $sortColumn ? ('desc' === $sortType ? 'asc' : 'desc') : $sortable,
                ]);
                $column->sorted = $name === $sortColumn ? $sortType : null;
            }
            $columns[] = $column;
        }

        $table = new Table($columns, $this->getRows(), $this->caption, $this->empty, $this->rowAttributes, attributes: $this->attributes);

        return Html::raw(($this->pager ? (string) new Pagination($this->pager, $this)->render() : '') . (string) $table->render());
    }

    private function prepareQuery(): string
    {
        $query = $this->query;
        $sql = Sql::factory($this->db);

        if (null !== $sortColumn = $this->getSortColumn()) {
            $sort = $sql->escapeIdentifier($sortColumn) . ' ' . ($this->getSortType() ?? 'asc');
            // the requested order replaces the default one, but not an order of the query itself
            if ($this->defaultSort || false === stripos($query, ' ORDER BY ')) {
                $query .= ' ORDER BY ' . $sort;
            }
        } elseif ($this->defaultSort) {
            $sort = [];
            foreach ($this->defaultSort as $column => $type) {
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
}
