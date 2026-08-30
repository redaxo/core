<?php

namespace Redaxo\Core\Database;

use DateTimeImmutable;
use Iterator;
use JsonException;
use Override;
use PDO;
use Pdo\Mysql;
use PDOException;
use PDOStatement;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\CouldNotConnectException;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use SensitiveParameter;
use Throwable;

use function array_key_exists;
use function assert;
use function date_default_timezone_get;
use function defined;
use function gettype;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function strlen;

use const E_USER_WARNING;
use const FILTER_FLAG_HOSTNAME;
use const FILTER_VALIDATE_DOMAIN;
use const JSON_THROW_ON_ERROR;
use const PHP_SAPI;

/**
 * Connect and interact with the database.
 *
 * @implements Iterator<int<0, max>, static>
 * @psalm-consistent-constructor
 */
class Sql implements Iterator
{
    use FactoryTrait;

    final public const string MYSQL = 'MySQL';
    final public const string MARIADB = 'MariaDB';

    final public const int ERROR_VIOLATE_UNIQUE_KEY = 1062;
    final public const string ERRNO_TABLE_OR_VIEW_DOESNT_EXIST = '42S02';

    /** Default SQL datetime format */
    final public const string FORMAT_DATETIME = 'Y-m-d H:i:s';

    /** Controls query buffering, view `Pdo\Mysql::ATTR_USE_BUFFERED_QUERY` for more details */
    final public const string OPT_BUFFERED = 'buffered';

    protected bool $debug = false; // debug schalter
    /** @var array<string, scalar|null> */
    protected array $values = []; // Werte von setValue
    /** @var array<string, string> */
    protected array $rawValues = []; // Werte von setRawValue
    /** @var list<string>|null */
    protected ?array $fieldnames = null; // Spalten im ResultSet
    /** @var list<string>|null */
    protected ?array $rawFieldnames = null;
    /** @var list<string>|null */
    protected ?array $tablenames = null; // Tabelle im ResultSet
    /** @var array<scalar|null>|null */
    protected ?array $lastRow = null; // Wert der zuletzt gefetchten zeile
    /** @var non-empty-string|null */
    protected ?string $table = null; // Tabelle setzen

    /**
     * Where condition as string or as nested array (see `setWhere` for examples).
     * @var string|array<scalar|array<scalar|array<mixed>>>|null
     */
    protected string|array|null $wherevar;

    /** @var array<scalar> */
    protected array $whereParams = [];

    protected int $rows = 0; // anzahl der treffer
    /** @var int<0, max> */
    protected int $counter = 0; // pointer

    protected string $query = ''; // Die Abfrage
    /** @var array<scalar|null> */
    protected array $params = []; // Die Abfrage-Parameter
    /** @var positive-int */
    protected int $DBID; // ID der Verbindung

    /** Store the lastInsertId per Sql object, so Sql objects don't override each other because of the shared static PDO instance.*/
    private ?int $lastInsertId = null;
    /** @var list<Sql> */
    protected array $records = [];

    protected ?PDOStatement $stmt = null;

    /** @var array<positive-int, Mysql> */
    protected static array $pdo = [];

    /** @param positive-int $db */
    protected function __construct(int $db = 1)
    {
        $this->flush();

        $this->DBID = $db;
    }

    /**
     * Creates a Sql instance.
     *
     * @param positive-int $db
     */
    public static function factory(int $db = 1): static
    {
        $class = static::getFactoryClass();
        return new $class($db);
    }

    /**
     * Stellt die Verbindung zur Datenbank her.
     *
     * @param positive-int $db
     * @throws SqlException
     */
    protected function selectDB(int $db): void
    {
        $this->DBID = $db;

        try {
            if (!isset(self::$pdo[$db])) {
                $dbconfig = Core::getDbConfig($db);

                $conn = self::createConnection(
                    $dbconfig->host,
                    $dbconfig->name,
                    $dbconfig->login,
                    $dbconfig->password,
                    $dbconfig->persistent,
                    self::createSslOptions($dbconfig),
                );
                self::$pdo[$db] = $conn;

                self::initSession($db);
            }
        } catch (PDOException $e) {
            if ('cli' === PHP_SAPI) {
                throw new CouldNotConnectException('Could not connect to database (DB: ' . $db . ").\n\nConsider starting either the web-based or console-based REDAXO setup to configure the database connection settings.", $e, $this);
            }
            throw new CouldNotConnectException('Could not connect to database (DB: ' . $db . ').', $e, $this);
        }
    }

    /**
     * Sets the session variables that every connection needs.
     *
     * Without an explicit time zone the session follows the database server while php follows its own
     * configuration, so `NOW()` and `timestamp` columns disagree with the times php writes. A named zone
     * is preferred as it survives a daylight saving change, but it needs the `mysql.time_zone%` tables,
     * which are usually empty. `CONVERT_TZ()` reports whether the zone is known without requiring access
     * to the `mysql` database, so the choice fits into this one statement.
     *
     * @param positive-int $db
     */
    private static function initSession(int $db): void
    {
        $timeZone = date_default_timezone_get();
        $parameters = [$timeZone];

        // Assigning the current value is a no-op, for the zones without any usable offset.
        $fallback = '@@session.time_zone';

        if (null !== $offset = self::getTimeZoneOffset()) {
            $fallback = '?';
            $parameters[] = $offset;
        }

        $parameters[] = $timeZone;

        self::factory($db)->setQuery(
            'SET SESSION SQL_MODE = "ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"'
            // The source zone is given as an offset, so that the probe only tells whether the target zone is known.
            . ', time_zone = IF(CONVERT_TZ("2000-01-01 00:00:00", "+00:00", ?) IS NULL, ' . $fallback . ', ?)',
            $parameters,
        );
    }

    /**
     * The current utc offset of php's time zone, or null when the database cannot express it.
     *
     * Being a snapshot, it goes stale for a process that runs across a daylight saving change.
     */
    private static function getTimeZoneOffset(): ?string
    {
        $now = new DateTimeImmutable();
        $offset = $now->getOffset();

        // MariaDB accepts `-12:59` to `+13:00` only, leaving out real zones like `Pacific/Kiritimati`
        // (+14:00). Setting one anyway would break the connection.
        if ($offset < -(12 * 3600 + 59 * 60) || $offset > 13 * 3600) {
            return null;
        }

        return $now->format('P');
    }

    /**
     * Returns the `Pdo\Mysql` instance, creates the database connection when not already created.
     *
     * @throws SqlException
     */
    public function getConnection(): Mysql
    {
        if (!isset(self::$pdo[$this->DBID])) {
            $this->selectDB($this->DBID);
        }

        return self::$pdo[$this->DBID];
    }

    /** @param string $host the host. might optionally include a port. */
    protected static function createConnection(
        #[SensitiveParameter] string $host,
        #[SensitiveParameter] string $database,
        #[SensitiveParameter] string $login,
        #[SensitiveParameter] string $password,
        bool $persistent = false,
        array $options = [],
    ): Mysql {
        if (!$database) {
            throw new InvalidArgumentException('Database name can not be empty.');
        }

        $port = null;
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
        }

        $dsn = 'mysql:host=' . $host;
        if ($port) {
            $dsn .= ';port=' . $port;
        }
        $dsn .= ';dbname=' . $database;
        $dsn .= ';charset=utf8mb4';

        // array_merge() doesnt work because it looses integer keys
        $options += [
            PDO::ATTR_PERSISTENT => $persistent,
            PDO::ATTR_FETCH_TABLE_NAMES => true,
        ];

        return @new Mysql($dsn, $login, $password, $options);
    }

    /**
     * Gibt die DatenbankId der Abfrage (SQL) zurueck,
     * oder false wenn die Abfrage keine DBID enthaelt.
     *
     * @return false|positive-int
     */
    protected static function getQueryDBID(string $query): int|false
    {
        $query = trim($query);

        if (preg_match('/\(DB([1-9]){1}\)/i', $query, $matches)) {
            $dbid = (int) $matches[1];
            assert($dbid > 0);
            return $dbid;
        }

        return false;
    }

    /**
     * Entfernt die DBID aus einer Abfrage (SQL) und gibt die DBID zurueck falls
     * vorhanden, sonst false.
     *
     * @return false|positive-int
     */
    protected static function stripQueryDBID(string &$query): int|false
    {
        $query = trim($query);

        if (false !== ($qryDBID = self::getQueryDBID($query))) {
            $query = substr($query, 6);
        }

        return $qryDBID;
    }

    /**
     * Gibt den Typ der Abfrage (SQL) zurueck,
     * oder false wenn die Abfrage keinen Typ enthaelt.
     *
     * Moegliche Typen:
     * - SELECT
     * - SHOW
     * - UPDATE
     * - INSERT
     * - DELETE
     * - REPLACE
     * - CREATE
     * - CALL
     * - OPTIMIZE
     */
    public static function getQueryType(string $query): string|false
    {
        $query = trim($query);
        // DBID aus dem Query herausschneiden, falls vorhanden
        self::stripQueryDBID($query);

        if (preg_match('/^\s*\(?\s*(SELECT|SHOW|UPDATE|INSERT|DELETE|REPLACE|CREATE|CALL|OPTIMIZE)/i', $query, $matches)) {
            return strtoupper($matches[1]);
        }

        return false;
    }

    /**
     * Returns a datetime string in sql datetime format (Y-m-d H:i:s) using the given timestamp or the current time
     * if no timestamp (or `null`) is given.
     */
    public static function datetime(?int $timestamp = null): string
    {
        return date(self::FORMAT_DATETIME, $timestamp ?? time());
    }

    /**
     * Setzt eine Abfrage (SQL) ab, wechselt die DBID falls vorhanden.
     *
     * Beispiel-Query: '(DB1) SELECT * FROM my_table WHERE my_col_int = 5'
     *
     * @param string $query The sql-query
     * @param array<scalar|null> $params An optional array of statement parameter
     * @param array<self::OPT_*, bool> $options For possible option keys view `Sql::OPT_*` constants
     *
     * @throws SqlException on errors
     *
     * @psalm-taint-sink sql $query
     * @psalm-taint-specialize
     */
    public function setDBQuery(string $query, array $params = [], array $options = []): static
    {
        // save origin connection-id
        $oldDBID = $this->DBID;

        try {
            // change connection-id but only for this one query
            if (false !== ($qryDBID = self::stripQueryDBID($query))) {
                $this->selectDB($qryDBID);
            }

            $this->setQuery($query, $params, $options);
        } finally {
            // restore connection-id
            $this->DBID = $oldDBID;
        }

        return $this;
    }

    /** Setzt Debugmodus an/aus. */
    public function setDebug(bool $debug = true): static
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Prepares a PDOStatement.
     *
     * @param string $query A query string with placeholders
     * @throws SqlException
     * @return PDOStatement The prepared statement
     *
     * @psalm-taint-sink sql $query
     */
    public function prepareQuery(string $query): PDOStatement
    {
        $pdo = $this->getConnection();
        try {
            $this->query = $query;
            $this->stmt = $pdo->prepare($query);
            return $this->stmt;
        } catch (PDOException $e) {
            throw new SqlException('Error while preparing statement "' . $query . '": ' . $e->getMessage(), $e, $this);
        }
    }

    /**
     * Executes the prepared statement with the given input parameters.
     *
     * @param array<scalar|null> $params Array of input parameters
     * @param array<self::OPT_*, bool> $options For possible option keys view `Sql::OPT_*` constants
     *
     * @throws SqlException
     */
    public function execute(array $params = [], array $options = []): static
    {
        if (!$this->stmt) {
            throw new SqlException('you need to prepare a query before calling execute().', null, $this);
        }

        $buffered = null;
        $pdo = $this->getConnection();
        if (isset($options[self::OPT_BUFFERED])) {
            $buffered = $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY);
            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, $options[self::OPT_BUFFERED]);
        }

        try {
            $this->flush();
            $this->params = $params;

            foreach ($params as $param => $value) {
                $param = is_int($param) ? $param + 1 : $param;
                $type = match (gettype($value)) {
                    'boolean' => PDO::PARAM_BOOL,
                    'integer' => PDO::PARAM_INT,
                    'NULL' => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $this->stmt->bindValue($param, $value, $type);
            }

            $this->stmt->execute();
            $this->rows = $this->stmt->rowCount();
            $this->lastInsertId = ((int) $this->getConnection()->lastInsertId()) ?: null;
        } catch (PDOException $e) {
            throw new SqlException('Error while executing statement "' . $this->query . '" using params ' . json_encode($params) . ': ' . $e->getMessage(), $e, $this);
        } finally {
            if (null !== $buffered) {
                $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, $buffered);
            }

            if ($this->debug) {
                $this->printError($this->query, $params);
            }
        }

        return $this;
    }

    /**
     * Executes the given sql-query.
     *
     * If parameters will be provided, a prepared statement will be executed.
     *
     * example 1:
     *    $sql->setQuery('SELECT * FROM mytable where id=:id', ['id' => 3]);
     *
     * NOTE: named-parameters/?-placeholders are not supported in LIMIT clause!
     *
     * @param string $query The sql-query
     * @param array<scalar|null> $params An optional array of statement parameter
     * @param array<self::OPT_*, bool> $options For possible option keys view `Sql::OPT_*` constants
     *
     * @throws SqlException on errors
     *
     * @psalm-taint-specialize
     */
    public function setQuery(string $query, array $params = [], array $options = []): static
    {
        // Alle Werte zuruecksetzen
        $this->flush();
        $this->query = $query;
        $this->params = $params;
        $this->stmt = null;

        $this->prepareQuery($query);
        $this->execute($params, $options);

        return $this;
    }

    /**
     * Setzt den Tabellennamen.
     *
     * @param string $table Tabellenname
     */
    public function setTable(string $table): static
    {
        if ('' === $table) {
            throw new InvalidArgumentException('Table name can not be empty');
        }

        $this->table = $table;

        return $this;
    }

    /**
     * Sets the raw value of a column.
     *
     * @param string $column Name of the column
     * @param string $value The raw value
     *
     * @psalm-taint-sink sql $value
     */
    public function setRawValue(string $column, string $value): static
    {
        $this->rawValues[$column] = $value;
        unset($this->values[$column]);

        return $this;
    }

    /**
     * Set the value of a column.
     *
     * @param string $column Name of the column
     * @param scalar|null $value The value
     */
    public function setValue(string $column, string|int|float|bool|null $value): static
    {
        $this->values[$column] = $value;
        unset($this->rawValues[$column]);

        return $this;
    }

    /**
     * Set the array value of a column (json encoded).
     *
     * @param string $column Name of the column
     * @param array<mixed> $value The value
     */
    public function setArrayValue(string $column, array $value): static
    {
        return $this->setValue($column, json_encode($value));
    }

    /**
     * Sets the datetime value of a column.
     *
     * @param string $column Name of the column
     * @param int|null $timestamp Unix timestamp (if `null` is given, the current time is used)
     */
    public function setDateTimeValue(string $column, ?int $timestamp): static
    {
        return $this->setValue($column, self::datetime($timestamp));
    }

    /**
     * Setzt ein Array von Werten zugleich.
     *
     * @param array<string, scalar|null> $valueArray Ein Array von Werten
     */
    public function setValues(array $valueArray): static
    {
        foreach ($valueArray as $name => $value) {
            $this->setValue($name, $value);
        }

        return $this;
    }

    /** Returns whether values are set inside this Sql object. */
    public function hasValues(): bool
    {
        return [] !== $this->values;
    }

    /**
     * Prueft den Wert der Spalte $column der aktuellen Zeile, ob $value enthalten ist.
     *
     * @param string $column Spaltenname des zu pruefenden Feldes
     * @param string $value Wert, der enthalten sein soll
     *
     * @throws SqlException
     */
    protected function isValueOf(string $column, string $value): bool
    {
        if ('' == $value) {
            return true;
        }
        return str_contains((string) $this->getValue($column), $value);
    }

    /**
     * Adds a record for multi row/batch operations.
     *
     * This method can only be used in combination with `insert()` and `replace()`.
     *
     * Example:
     *      $sql->addRecord(function (Sql $record) {
     *          $record->setValue('title', 'Foo');
     *          $record->setRawValue('created', 'NOW()');
     *      });
     *
     * @param callable(self):void $callback The callback receives a new `Sql` instance for the new record
     *                           and must set the values of the new record on that instance (see example above)
     */
    public function addRecord(callable $callback): static
    {
        $record = self::factory($this->DBID);

        $callback($record);

        $this->records[] = $record;

        return $this;
    }

    /**
     * Setzt die WHERE Bedienung der Abfrage.
     *
     * example 1:
     *    $sql->setWhere(['id' => 3, 'field' => '']); // results in id = 3 AND field = ''
     *    $sql->setWhere([['id' => 3, 'field' => '']]); // results in id = 3 OR field = ''
     *
     * example 2:
     *    $sql->setWhere('myid = :id OR anotherfield = :field', ['id' => 3, 'field' => '']);
     *
     * example 3 (deprecated):
     *    $sql->setWhere('myid="35" OR abc="zdf"');
     *
     * @param string|array<scalar|array<scalar|array<mixed>>> $where
     * @param array<scalar> $params
     */
    public function setWhere(string|array $where, array $params = []): static
    {
        if (is_array($where)) {
            $this->wherevar = $where;
            $this->whereParams = [];
        } else {
            $this->wherevar = 'WHERE ' . $where;
            $this->whereParams = $params;
        }

        return $this;
    }

    /**
     * Returns the tuple of `where` string and `where` params.
     *
     * @return list{string, array<scalar>}
     */
    private function buildWhere(): array
    {
        if (!$this->wherevar) {
            return ['', []];
        }

        if (is_string($this->wherevar)) {
            return [$this->wherevar, $this->whereParams];
        }

        $whereParams = [];
        $where = $this->buildWhereArg($this->wherevar, $whereParams);

        return ['WHERE ' . $where, $whereParams];
    }

    /**
     * Concats the given array to a sql condition using bound parameters.
     * AND/OR opartors are alternated depending on $level.
     *
     * @param array<scalar|array<scalar|array<mixed>>> $columns
     * @param array<scalar> $params
     */
    private function buildWhereArg(array $columns, array &$params, int $level = 0): string
    {
        if (1 == $level % 2) {
            $op = ' OR ';
        } else {
            $op = ' AND ';
        }

        $qry = '';
        foreach ($columns as $fldName => $value) {
            if (is_array($value)) {
                /** @var array<scalar|array<scalar|array<mixed>>> $value */
                $arg = '(' . $this->buildWhereArg($value, $params, $level + 1) . ')';
            } else {
                $paramName = $fldName;
                for ($i = 1; array_key_exists($paramName, $params) || array_key_exists($paramName, $this->values); ++$i) {
                    $paramName = $fldName . '_' . $i;
                }

                $arg = $this->escapeIdentifier($fldName) . ' = :' . $paramName;
                $params[$paramName] = $value;
            }

            if ('' != $qry) {
                $qry .= $op;
            }
            $qry .= $arg;
        }
        return $qry;
    }

    /**
     * Returns the value of a column.
     *
     * @param string $column Name of the column
     * @throws SqlException
     * @return scalar|null
     *
     * @psalm-taint-source input
     */
    public function getValue(string $column): string|int|float|bool|null
    {
        if (empty($column)) {
            throw new InvalidArgumentException('Parameter $column must not be empty.');
        }

        // fast fail,... value already set manually?
        if (isset($this->values[$column])) {
            return $this->values[$column];
        }

        // check if there is an table alias defined
        // if not, try to guess the tablename
        if (!str_contains($column, '.')) {
            $tables = $this->getTablenames();
            foreach ($tables as $table) {
                if (in_array($table . '.' . $column, $this->rawFieldnames)) {
                    return $this->fetchValue($table . '.' . $column);
                }
            }
        }

        return $this->fetchValue($column);
    }

    /**
     * Returns the array value of a (json encoded) column.
     *
     * @param string $column Name of the column
     * @throws SqlException
     * @return array<mixed>
     */
    public function getArrayValue(string $column): array
    {
        $value = $this->getValue($column);
        if (null === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (JsonException $e) {
            throw new SqlException('Failed to decode json value of column "' . $column . '": ' . $e->getMessage(), $e);
        }

        throw new SqlException('Failed to decode json value of column "' . $column . '" as array.');
    }

    /**
     * Returns the unix timestamp of a datetime column.
     *
     * @param string $column Name of the column
     * @throws SqlException
     * @return int|null Unix timestamp or `null` if the column is `null` or not in sql datetime format
     */
    public function getDateTimeValue(string $column): ?int
    {
        $value = $this->getValue($column);
        return $value ? strtotime($value) : null;
    }

    /** @return scalar|null */
    protected function fetchValue(string $column): string|int|float|bool|null
    {
        if (isset($this->values[$column])) {
            return $this->values[$column];
        }

        if (empty($this->lastRow)) {
            // no row fetched, but also no query was executed before
            if (null == $this->stmt) {
                return null;
            }
            $this->getRow(PDO::FETCH_ASSOC);
        }

        // isset() alone doesn't work here, because values may also be null
        if (is_array($this->lastRow) && (isset($this->lastRow[$column]) || array_key_exists($column, $this->lastRow))) {
            return $this->lastRow[$column];
        }
        trigger_error('Field "' . $column . '" does not exist in result!', E_USER_WARNING);
        return null;
    }

    /**
     * Gibt den Wert der aktuellen Zeile im ResultSet zurueck
     * Falls es noch keine erste Zeile (lastRow) gibt, wird der Satzzeiger
     * initialisiert. Weitere Satzwechsel mittels next().
     *
     * @template TFetchType of PDO::FETCH_ASSOC|PDO::FETCH_NUM
     * @param TFetchType $fetchType
     * @psalm-return (TFetchType is PDO::FETCH_NUM ? list<scalar|null> : array<string, scalar|null>)
     *
     * @psalm-taint-source input
     */
    public function getRow(int $fetchType = PDO::FETCH_ASSOC): array
    {
        if (!$this->lastRow) {
            $lastRow = $this->stmt->fetch($fetchType);
            if (false === $lastRow) {
                throw new SqlException('Unable to fetch row for statement "' . $this->query . '".', null, $this);
            }
            /** @var array<scalar|null> $lastRow */
            $this->lastRow = $lastRow;
        }
        return $this->lastRow;
    }

    /**
     * Prueft, ob eine Spalte im Resultset vorhanden ist.
     *
     * @template T as string
     * @param T $column Name der Spalte
     *
     * @psalm-assert-if-true !null $this->isNull(T)
     */
    public function hasValue(string $column): bool
    {
        // fast fail,... value already set manually?
        if (isset($this->values[$column])) {
            return true;
        }

        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            return in_array($parts[0], $this->getTablenames()) && in_array($parts[1], $this->getFieldnames());
        }
        return in_array($column, $this->getFieldnames());
    }

    /**
     * Prueft, ob das Feld mit dem Namen $feldname Null ist.
     *
     * Falls das Feld nicht vorhanden ist,
     * wird Null zurueckgegeben, sonst True/False
     *
     * @throws SqlException
     */
    public function isNull(string $column): ?bool
    {
        if ($this->hasValue($column)) {
            return null === $this->getValue($column);
        }

        return null;
    }

    /**
     * Gibt die Anzahl der Zeilen zurueck.
     *
     * @phpstan-impure
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /** Gibt die Anzahl der Felder/Spalten zurueck. */
    public function getFields(): int
    {
        return $this->stmt ? $this->stmt->columnCount() : 0;
    }

    /**
     * Baut den SET bestandteil mit der
     * verfuegbaren values zusammen und gibt diesen zurueck.
     *
     * @see setValue
     */
    protected function buildPreparedValues(): string
    {
        $qry = '';
        foreach ($this->values as $fldName => $value) {
            if ('' != $qry) {
                $qry .= ', ';
            }

            /** @psalm-taint-escape sql */ // psalm marks whole array (keys and values) as tainted, not values only
            $set = $this->escapeIdentifier($fldName) . ' = :' . $fldName;
            $qry .= $set;
        }
        foreach ($this->rawValues as $fldName => $value) {
            if ('' != $qry) {
                $qry .= ', ';
            }

            $qry .= $this->escapeIdentifier($fldName) . ' = ' . $value;
        }

        if ('' == trim($qry)) {
            // FIXME
            trigger_error('no values given to buildPreparedValues for update(), insert() or replace()', E_USER_WARNING);
        }

        return $qry;
    }

    public function getWhere(): string
    {
        [$where] = $this->buildWhere();

        return $where;
    }

    /**
     * Setzt eine Select-Anweisung auf die angegebene Tabelle
     * mit den WHERE Parametern ab.
     *
     * @throws SqlException
     *
     * @psalm-taint-sink sql $columns
     */
    public function select(string $columns = '*'): static
    {
        [$where, $whereParams] = $this->buildWhere();

        $this->setQuery(
            'SELECT ' . $columns . ' FROM ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' ' . $where,
            $whereParams,
        );
        return $this;
    }

    /**
     * Setzt eine Update-Anweisung auf die angegebene Tabelle
     * mit den angegebenen Werten und WHERE Parametern ab.
     *
     * @throws SqlException
     */
    public function update(): static
    {
        [$where, $whereParams] = $this->buildWhere();

        $this->setQuery(
            'UPDATE ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' SET ' . $this->buildPreparedValues() . ' ' . $where,
            array_merge($this->values, $whereParams),
        );
        return $this;
    }

    /**
     * Setzt eine Insert-Anweisung auf die angegebene Tabelle
     * mit den angegebenen Werten ab.
     *
     * @throws SqlException
     */
    public function insert(): static
    {
        if ($this->records) {
            return $this->setMultiRecordQuery('INSERT');
        }

        // hold a copies of the query fields for later debug out (the class property will be reverted in setQuery())
        $tableName = Type::notNull($this->table);
        $values = $this->values;

        if ($this->values || $this->rawValues) {
            $setValues = 'SET ' . $this->buildPreparedValues();
        } else {
            $setValues = 'VALUES ()';
        }

        $this->setQuery(
            'INSERT INTO ' . $this->escapeIdentifier($tableName) . ' ' . $setValues,
            $this->values,
        );

        // provide debug infos, if insert is considered successfull, but no rows were inserted.
        // this happens when you violate against a NOTNULL constraint
        if (0 == $this->getRows()) {
            /** @psalm-taint-escape html */ // https://github.com/vimeo/psalm/issues/4669
            $printValues = $values;
            $printValues = print_r($printValues, true);
            throw new SqlException('Error while inserting into table "' . $tableName . '" with values ' . $printValues . '. Check your null/not-null constraints.', null, $this);
        }
        return $this;
    }

    /** @throws SqlException */
    public function insertOrUpdate(): static
    {
        if ($this->records) {
            return $this->setMultiRecordQuery('INSERT', true);
        }

        $onDuplicateKeyUpdate = $this->buildOnDuplicateKeyUpdate(array_keys(array_merge($this->values, $this->rawValues)));
        $this->setQuery(
            'INSERT INTO ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' SET ' . $this->buildPreparedValues() . ' ' . $onDuplicateKeyUpdate,
            $this->values,
        );

        return $this;
    }

    /**
     * Setzt eine Replace-Anweisung auf die angegebene Tabelle
     * mit den angegebenen Werten ab.
     *
     * REPLACE works exactly like INSERT, except that if an old row in the table
     * has the same value as a new row for a PRIMARY KEY or a UNIQUE index,
     * the old row is deleted before the new row is inserted.
     *
     * @throws SqlException
     */
    public function replace(): static
    {
        if ($this->records) {
            return $this->setMultiRecordQuery('REPLACE');
        }

        [$where, $whereParams] = $this->buildWhere();

        $this->setQuery(
            'REPLACE INTO ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' SET ' . $this->buildPreparedValues() . ' ' . $where,
            array_merge($this->values, $whereParams),
        );
        return $this;
    }

    /**
     * Setzt eine Delete-Anweisung auf die angegebene Tabelle
     * mit den angegebenen WHERE Parametern ab.
     *
     * @throws SqlException
     */
    public function delete(): static
    {
        [$where, $whereParams] = $this->buildWhere();

        $this->setQuery(
            'DELETE FROM ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' ' . $where,
            $whereParams,
        );
        return $this;
    }

    /** Stellt alle Werte auf den Ursprungszustand zurueck. */
    private function flush(): static
    {
        $this->values = [];
        $this->rawValues = [];
        $this->records = [];
        $this->whereParams = [];
        $this->lastRow = null;
        $this->fieldnames = null;
        $this->rawFieldnames = null;
        $this->tablenames = null;

        $this->table = null;
        $this->wherevar = null;
        $this->counter = 0;
        $this->rows = 0;
        $this->lastInsertId = null;

        return $this;
    }

    /**
     * Stellt alle Values, die mit setValue() gesetzt wurden, zurueck.
     *
     * @see setValue(), #getValue()
     */
    public function flushValues(): static
    {
        $this->values = [];
        $this->rawValues = [];

        return $this;
    }

    /** Prueft ob das Resultset weitere Datensaetze enthaelt. */
    public function hasNext(): bool
    {
        return $this->counter < $this->rows;
    }

    /**
     * Setzt den Cursor des Resultsets zurueck zum Anfang.
     *
     * @throws SqlException
     */
    public function reset(): static
    {
        // re-execute the statement
        if ($this->stmt && 0 !== $this->counter) {
            $this->execute($this->params);
            $this->counter = 0;
        }

        return $this;
    }

    /** Gibt die letzte InsertId zurueck. */
    public function getLastId(): int
    {
        if (null === $this->lastInsertId) {
            throw new SqlException('No last insert id available.', null, $this);
        }

        return $this->lastInsertId;
    }

    /**
     * Laedt das komplette Resultset in ein Array und gibt dieses zurueck und
     * wechselt die DBID falls vorhanden.
     *
     * @template TFetchType as PDO::FETCH_ASSOC|PDO::FETCH_NUM|PDO::FETCH_KEY_PAIR
     *
     * @param string|null $query The sql-query
     * @param array<scalar|null> $params An optional array of statement parameter
     * @param TFetchType $fetchType
     *
     * @throws SqlException on errors
     *
     * @return (
     *    TFetchType is PDO::FETCH_KEY_PAIR ? array<int|string, scalar|null> :
     *    (
     *       TFetchType is PDO::FETCH_NUM ? list<array<int, scalar|null>> :
     *         list<array<string, scalar|null>>
     *    ))
     *
     * @psalm-taint-source input
     * @psalm-taint-sink sql $query
     */
    public function getDBArray(?string $query = null, array $params = [], int $fetchType = PDO::FETCH_ASSOC): array
    {
        if (!$query) {
            $query = $this->query;
            $params = $this->params;
        }

        $pdo = $this->getConnection();

        $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, false);
        $this->setDBQuery($query, $params);
        $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, true);

        return $this->stmt->fetchAll($fetchType);
    }

    /**
     * Laedt das komplette Resultset in ein Array und gibt dieses zurueck.
     *
     * @template TFetchType as PDO::FETCH_ASSOC|PDO::FETCH_NUM|PDO::FETCH_KEY_PAIR
     *
     * @param string|null $query The sql-query
     * @param array<scalar|null> $params An optional array of statement parameter
     * @param TFetchType $fetchType
     *
     * @throws SqlException on errors
     *
     * @return (
     *    TFetchType is PDO::FETCH_KEY_PAIR ? array<int|string, scalar|null> :
     *    (
     *       TFetchType is PDO::FETCH_NUM ? list<array<int, scalar|null>> :
     *         list<array<string, scalar|null>>
     *    ))
     *
     * @psalm-taint-source input
     * @psalm-taint-sink sql $query
     */
    public function getArray(?string $query = null, array $params = [], int $fetchType = PDO::FETCH_ASSOC): array
    {
        if (!$query) {
            $query = $this->query;
            $params = $this->params;
        }

        $pdo = $this->getConnection();

        $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, false);
        $this->setQuery($query, $params);
        $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, true);

        return $this->stmt->fetchAll($fetchType);
    }

    /** Gibt die zuletzt aufgetretene Fehlernummer zurueck. */
    public function getErrno(): ?string
    {
        return $this->stmt ? $this->stmt->errorCode() : $this->getConnection()->errorCode();
    }

    public function getMysqlErrno(): int
    {
        $errorInfos = $this->stmt ? $this->stmt->errorInfo() : $this->getConnection()->errorInfo();

        return (int) $errorInfos[1];
    }

    /**
     * Gibt den zuletzt aufgetretene Fehler zurueck.
     *
     * @phpstan-impure
     */
    public function getError(): ?string
    {
        $errorInfos = $this->stmt ? $this->stmt->errorInfo() : $this->getConnection()->errorInfo();
        // idx0   SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
        // idx1   Driver-specific error code.
        // idx2   Driver-specific error message.
        return $errorInfos[2];
    }

    /** Prueft, ob ein Fehler aufgetreten ist. */
    public function hasError(): bool
    {
        return 0 != $this->getErrno();
    }

    /**
     * Gibt die letzte Fehlermeldung aus.
     *
     * @param array<scalar|null> $params
     */
    protected function printError(string $query, array $params): void
    {
        $errors = [];
        $errors['query'] = $query;
        if (!empty($params)) {
            $errors['params'] = $params;

            // taken from https://github.com/doctrine/DoctrineBundle/blob/d57c1a35cd32e6b942fdda90ae3888cc1bb41e6b/Twig/DoctrineExtension.php#L290-L305
            $i = 0;
            $errors['fullquery'] = preg_replace_callback(
                '/\?|((?<!:):[a-z0-9_]+)/i',
                function (array $matches) use ($params, &$i): string {
                    if ('?' === $matches[0]) {
                        $keys = [$i];
                    } else {
                        $keys = [$matches[0], substr($matches[0], 1)];
                    }

                    foreach ($keys as $key) {
                        if (!array_key_exists($key, $params)) {
                            continue;
                        }

                        ++$i;
                        return match (gettype($params[$key])) {
                            'boolean' => $params[$key] ? '1' : '0',
                            'integer' => (string) $params[$key],
                            'NULL' => 'NULL',
                            default => $this->escape((string) $params[$key]),
                        };
                    }

                    return $matches[0];
                },
                $query,
            );
        }
        if ($this->getRows()) {
            $errors['count'] = $this->getRows();
        }
        if ($this->getError()) {
            $errors['error'] = $this->getError();
            $errors['ecode'] = $this->getErrno();
        }
        /** @psalm-suppress ForbiddenCode */
        dump($errors);
    }

    /**
     * Setzt eine Spalte auf den naechst moeglich auto_increment Wert.
     *
     * @param string $column Name der Spalte
     * @throws SqlException
     */
    public function setNewId(string $column, int $startId = 0): int
    {
        // setNewId muss neues sql Objekt verwenden, da sonst bestehende informationen im Objekt ueberschrieben werden
        $sql = self::factory();
        $sql->setQuery('SELECT ' . $this->escapeIdentifier($column) . ' FROM ' . $this->escapeIdentifier(Type::notNull($this->table)) . ' ORDER BY ' . $this->escapeIdentifier($column) . ' DESC LIMIT 1');
        if (0 == $sql->getRows()) {
            $id = $startId;
        } else {
            $id = (int) $sql->getValue($column);
        }
        ++$id;
        $this->setValue($column, $id);

        return $id;
    }

    /**
     * Gibt die Spaltennamen des ResultSets zurueck.
     *
     * @return list<string>
     */
    public function getFieldnames(): array
    {
        $this->fetchMeta();

        return $this->fieldnames;
    }

    /** @return list<string> */
    public function getTablenames(): array
    {
        $this->fetchMeta();

        return $this->tablenames;
    }

    /**
     * @psalm-assert !null $this->fieldnames
     * @psalm-assert !null $this->rawFieldnames
     * @psalm-assert !null $this->tablenames
     */
    private function fetchMeta(): void
    {
        if (null === $this->fieldnames) {
            $this->rawFieldnames = [];
            $this->fieldnames = [];
            $this->tablenames = [];

            $stripTableName = null;
            for ($i = 0; $i < $this->getFields(); ++$i) {
                $metadata = $this->stmt->getColumnMeta($i);

                $this->rawFieldnames[] = $metadata['name'];

                if (null === $stripTableName) {
                    $stripTableName = str_starts_with($metadata['name'], $metadata['table'] . '.');
                }
                if ($stripTableName) {
                    $metadata['name'] = substr($metadata['name'], strlen($metadata['table'] . '.'));
                }

                $this->fieldnames[] = $metadata['name'];

                if (!in_array($metadata['table'], $this->tablenames)) {
                    $this->tablenames[] = $metadata['table'];
                }
            }
        }
    }

    /**
     * Escaped den uebergeben Wert fuer den DB Query.
     *
     * @return non-falsy-string
     *
     * @psalm-taint-escape sql
     */
    public function escape(string $value): string
    {
        return $this->getConnection()->quote($value);
    }

    /**
     * Escapes and adds backsticks around.
     *
     * @psalm-taint-escape sql
     */
    public function escapeIdentifier(string $name): string
    {
        return self::_escapeIdentifier($name);
    }

    /**
     * Escapes and adds backsticks around.
     *
     * @psalm-taint-escape sql
     */
    private static function _escapeIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Escapes the `LIKE` wildcard chars "%" and "_" in given value.
     *
     * @return ($value is numeric-string ? numeric-string :
     *   ($value is non-falsy-string ? non-falsy-string :
     *   ($value is non-empty-string ? non-empty-string : string
     * )))
     * @psalm-pure
     */
    public function escapeLikeWildcards(string $value): string
    {
        return str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $value);
    }

    /**
     * Escapes and transforms values for `IN (...)` clause.
     *
     * Example: `$sql->setQuery('SELECT * FROM my_table WHERE foo IN ('.$sql->in($values).')');`
     *
     * @param list<int>|list<string> $values
     *
     * @psalm-taint-escape sql
     */
    public function in(array $values): string
    {
        $strings = false;

        foreach ($values as $value) {
            if (is_int($value)) {
                continue;
            }
            if (is_string($value)) {
                $strings = true;
                continue;
            }

            throw new InvalidArgumentException('Argument $values must be an array of ints and/or strings, but it contains "' . get_debug_type($value) . '"');
        }

        if ($strings) {
            $values = array_map(function ($value): string {
                return $this->escape((string) $value);
            }, $values);
        }

        return implode(', ', $values);
    }

    /** @param string|null $user the name of the user who created the dataset. Defaults to the current user */
    public function addGlobalUpdateFields(?string $user = null): static
    {
        if (!$user) {
            $user = Core::getUser()->login ?? Core::getEnvironment()->value;
        }

        $this->setDateTimeValue('updatedate', time());
        $this->setValue('updateuser', $user);

        return $this;
    }

    /** @param string|null $user the name of the user who updated the dataset. Defaults to the current user */
    public function addGlobalCreateFields(?string $user = null): static
    {
        if (!$user) {
            $user = Core::getUser()->login ?? Core::getEnvironment()->value;
        }

        $this->setDateTimeValue('createdate', time());
        $this->setValue('createuser', $user);

        return $this;
    }

    /**
     * Starts a database transaction.
     *
     * @throws SqlException when a transaction is already running
     *
     * @return bool Indicating whether the transaction was successfully started
     */
    public function beginTransaction(): bool
    {
        if ($this->getConnection()->inTransaction()) {
            throw new SqlException('Transaction already started.', null, $this);
        }
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Rollback a already started database transaction.
     *
     * @throws SqlException when no transaction was started beforehand
     *
     * @return bool Indicating whether the transaction was successfully rollbacked
     */
    public function rollBack(): bool
    {
        if (!$this->getConnection()->inTransaction()) {
            throw new SqlException('Unable to rollback, no transaction started before.', null, $this);
        }
        return $this->getConnection()->rollBack();
    }

    /**
     * Commit a already started database transaction.
     *
     * @throws SqlException when no transaction was started beforehand
     *
     * @return bool Indicating whether the transaction was successfully committed
     */
    public function commit(): bool
    {
        if (!$this->getConnection()->inTransaction()) {
            throw new SqlException('Unable to commit, no transaction started before', null, $this);
        }
        return $this->getConnection()->commit();
    }

    /** @return bool whether a transaction was already started/is already running */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * Convenience method which executes the given callable within a transaction.
     *
     * In case the callable throws, the transaction will automatically rolled back.
     * In case no error happens, the transaction will be committed after the callable was called.
     *
     * @template T
     * @param callable():T $callable
     * @return T
     */
    public function transactional(callable $callable): mixed
    {
        $connection = $this->getConnection();
        $inTransaction = $connection->inTransaction();

        if (!$inTransaction) {
            $connection->beginTransaction();
        }
        try {
            $result = $callable();
            if (!$inTransaction) {
                $connection->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if (!$inTransaction) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    // ----------------- iterator interface

    /** @see https://www.php.net/manual/en/iterator.rewind.php */
    #[Override]
    public function rewind(): void
    {
        $this->reset();
    }

    /** @see https://www.php.net/manual/en/iterator.current.php */
    #[Override]
    public function current(): static
    {
        return $this;
    }

    /** @see https://www.php.net/manual/en/iterator.key.php */
    #[Override]
    public function key(): int
    {
        return $this->counter;
    }

    /** @see https://www.php.net/manual/en/iterator.next.php */
    #[Override]
    public function next(): void
    {
        ++$this->counter;
        $this->lastRow = null;
    }

    /** @see https://www.php.net/manual/en/iterator.valid.php */
    #[Override]
    public function valid(): bool
    {
        return $this->hasNext();
    }

    // ----------------- /iterator interface

    /**
     * Erstellt das CREATE TABLE Statement um die Tabelle $table
     * der Datenbankverbindung $DBID zu erstellen.
     *
     * @param string $table Name der Tabelle
     * @param positive-int $db Id der Datenbankverbindung
     *
     * @throws SqlException
     *
     * @return string CREATE TABLE Sql-Statement zu erstellung der Tabelle
     */
    public static function showCreateTable(string $table, int $db = 1): string
    {
        $sql = self::factory($db);
        $sql->setQuery('SHOW CREATE TABLE ' . $sql->escapeIdentifier($table));

        if (!$sql->getRows()) {
            throw new SqlException(sprintf('Table "%s" does not exist.', $table));
        }
        if (!$sql->hasValue('Create Table')) {
            throw new SqlException(sprintf('Table "%s" does not exist, it is a view instead.', $table));
        }

        return $sql->getValue('Create Table');
    }

    /**
     * Sucht alle Tabellen/Views der Datenbankverbindung $DBID.
     * Falls $tablePrefix gesetzt ist, werden nur dem Prefix entsprechende Tabellen gesucht.
     *
     * @param string|null $tablePrefix Zu suchender Tabellennamen-Prefix
     *
     * @throws SqlException
     *
     * @return list<string> Ein Array von Tabellennamen
     */
    public function getTablesAndViews(?string $tablePrefix = null): array
    {
        return $this->fetchTablesAndViews($tablePrefix);
    }

    /**
     * Sucht alle Tabellen der Datenbankverbindung $DBID.
     * Falls $tablePrefix gesetzt ist, werden nur dem Prefix entsprechende Tabellen gesucht.
     *
     * @param string|null $tablePrefix Zu suchender Tabellennamen-Prefix
     *
     * @throws SqlException
     *
     * @return list<string> Ein Array von Tabellennamen
     */
    public function getTables(?string $tablePrefix = null): array
    {
        return $this->fetchTablesAndViews($tablePrefix, 'Table_type = "BASE TABLE"');
    }

    /**
     * Sucht alle Views der Datenbankverbindung $DBID.
     * Falls $tablePrefix gesetzt ist, werden nur dem Prefix entsprechende Views gesucht.
     *
     * @param string|null $tablePrefix Zu suchender Tabellennamen-Prefix
     *
     * @throws SqlException
     *
     * @return list<string> Ein Array von Viewnamen
     */
    public function getViews(?string $tablePrefix = null): array
    {
        return $this->fetchTablesAndViews($tablePrefix, 'Table_type = "VIEW"');
    }

    /**
     * @throws SqlException
     * @return list<string>
     */
    private function fetchTablesAndViews(?string $tablePrefix = null, ?string $where = null): array
    {
        $dbConfig = Core::getDbConfig($this->DBID);

        $qry = 'SHOW FULL TABLES';

        $where = $where ? [$where] : [];

        if (null != $tablePrefix) {
            $column = $this->escapeIdentifier('Tables_in_' . $dbConfig->name);
            $where[] = $column . ' LIKE ' . $this->escape($this->escapeLikeWildcards($tablePrefix) . '%');
        }

        if ($where) {
            $qry .= ' WHERE ' . implode(' AND ', $where);
        }

        $tables = $this->getArray($qry);

        return array_map(static function (array $table): string {
            return Type::string(reset($table));
        }, $tables);
    }

    /**
     * Sucht Spalteninformationen der Tabelle $table der Datenbankverbindung $DBID.
     *
     * Beispiel fuer den Rueckgabewert:
     *
     * Array (
     *  [0] => Array (
     *    [name] => pid
     *    [type] => int(11)
     *    [null] => NO
     *    [key] => PRI
     *    [default] =>
     *    [extra] => auto_increment
     *  )
     *  [1] => Array (
     *    [name] => id
     *    [type] => int(11)
     *    [null] => NO
     *    [key] => MUL
     *    [default] =>
     *    [extra] =>
     *  )
     * )
     *
     * @param string $table Name der Tabelle
     * @param positive-int $db Id der Datenbankverbindung
     *
     * @throws SqlException
     *
     * @return list<array{name: string, type: string, null: 'YES'|'NO', key: string, default: string|null, extra: string, comment: string|null}> Ein mehrdimensionales Array das die Metadaten enthaelt
     */
    public static function showColumns(string $table, int $db = 1): array
    {
        $sql = self::factory($db);
        $sql->setQuery('SHOW FULL COLUMNS FROM ' . $sql->escapeIdentifier($table));

        $columns = [];
        foreach ($sql as $col) {
            $null = (string) $col->getValue('Null');
            assert('YES' === $null || 'NO' === $null);

            /** @psalm-taint-escape sql */
            $column = [
                'name' => (string) $col->getValue('Field'),
                'type' => (string) $col->getValue('Type'),
                'null' => $null,
                'key' => (string) $col->getValue('Key'),
                'default' => null === $col->getValue('Default') ? null : (string) $col->getValue('Default'),
                'extra' => (string) $col->getValue('Extra'),
                'comment' => null === $col->getValue('Comment') ? null : (string) $col->getValue('Comment'),
            ];

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Returns the full database version string.
     *
     * @param positive-int $db
     * @return string E.g. "5.7.7" or "5.5.5-10.4.9-MariaDB"
     */
    public static function getServerVersion(int $db = 1): string
    {
        return Type::string(self::factory($db)->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION));
    }

    /**
     * Returns the database type (MySQL or MariaDB).
     *
     * @return self::MYSQL|self::MARIADB
     */
    public function getDbType(): string
    {
        $version = $this->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return false === stripos($version, 'mariadb') ? self::MYSQL : self::MARIADB;
    }

    /**
     * Returns the normalized database version.
     *
     * @return string E.g. "5.7.7" or "10.4.9"
     */
    public function getDbVersion(): string
    {
        $version = $this->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (preg_match('/^(\d+\.\d+\.\d+)(?:-(\d+\.\d+\.\d+)-mariadb)?/i', $version, $match)) {
            return $match[2] ?? $match[1];
        }

        return $version;
    }

    public static function closeConnection(int $db = 1): void
    {
        unset(self::$pdo[$db]);
    }

    /**
     * Prueft die uebergebenen Zugangsdaten auf gueltigkeit und legt ggf. die
     * Datenbank an.
     *
     * @param string $host the host. might optionally include a port.
     * @param array<array-key, bool|string> $options Additional PDO options (e.g., SSL options)
     */
    public static function checkDbConnection(
        #[SensitiveParameter] string $host,
        #[SensitiveParameter] string $login,
        #[SensitiveParameter] string $password,
        #[SensitiveParameter] string $database,
        bool $createDb = false,
        array $options = [],
    ): true|string {
        if (!$database) {
            return I18n::msg('sql_database_name_missing');
        }

        if (str_contains($host, ':')) {
            [$hostName, $port] = explode(':', $host, 2);
            if (!filter_var($hostName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                return I18n::msg('sql_database_host_invalid', $hostName);
            }
        } else {
            if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                return I18n::msg('sql_database_host_invalid', $host);
            }
        }

        $errMsg = true;

        try {
            self::createConnection(
                $host,
                $database,
                $login,
                $password,
                false,
                $options,
            );

            // db connection was successfully established, but we were meant to create the db
            if ($createDb) {
                // -> throw db already exists error
                $errMsg = I18n::msg('sql_database_already_exists');
            }
        } catch (PDOException $e) {
            // see client mysql error codes at https://dev.mysql.com/doc/mysql-errors/8.0/en/client-error-reference.html

            // ER_BAD_HOST
            if (str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002]')) {
                // unable to connect to db server
                $errMsg = I18n::msg('sql_unable_to_connect_database');
            }
            // ER_BAD_DB_ERROR
            elseif (str_contains($e->getMessage(), 'SQLSTATE[HY000] [1049]') || str_contains($e->getMessage(), 'SQLSTATE[42000]')) {
                if ($createDb) {
                    try {
                        // use the "mysql" db for the connection
                        $conn = self::createConnection(
                            $host,
                            'mysql',
                            $login,
                            $password,
                            false,
                            $options,
                        );

                        if (1 !== $conn->exec('CREATE DATABASE ' . self::_escapeIdentifier($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
                            // unable to create db
                            $errMsg = I18n::msg('sql_unable_to_create_database');
                        }
                    } catch (PDOException) {
                        // unable to find database
                        $errMsg = I18n::msg('sql_unable_to_open_database');
                    }
                } else {
                    // unable to find database
                    $errMsg = I18n::msg('sql_unable_to_find_database');
                }
            }
            // ER_ACCESS_DENIED_ERROR
            // ER_DBACCESS_DENIED_ERROR
            // ER_ACCESS_DENIED_NO_PASSWORD_ERROR
            elseif (
                str_contains($e->getMessage(), 'SQLSTATE[HY000] [1045]')
                || str_contains($e->getMessage(), 'SQLSTATE[28000]')
                || str_contains($e->getMessage(), 'SQLSTATE[HY000] [1044]')
                || str_contains($e->getMessage(), 'SQLSTATE[HY000] [1698]')
            ) {
                // unable to connect to db
                $errMsg = I18n::msg('sql_unable_to_connect_database');
            }
            // ER_ACCESS_TO_SERVER_ERROR
            elseif (
                str_contains($e->getMessage(), 'SQLSTATE[HY000] [2005]')
            ) {
                // unable to connect to server
                $errMsg = I18n::msg('sql_unable_to_connect_server');
            } else {
                // we didn't expected this error, so rethrow it to show it to the admin/end-user
                throw $e;
            }
        }

        return $errMsg;
    }

    /**
     * Create SSL options array from database configuration.
     *
     * @return array<array-key, bool|string> SSL options for PDO connection
     *
     * @internal
     */
    public static function createSslOptions(Configuration $dbConfig): array
    {
        $options = [];

        if ($dbConfig->sslKey && $dbConfig->sslCert) {
            $options = [
                Mysql::ATTR_SSL_KEY => $dbConfig->sslKey,
                Mysql::ATTR_SSL_CERT => $dbConfig->sslCert,
            ];
        }
        if (true === $dbConfig->sslCa) {
            // ssl_ca = true enables SSL CA verification without specific file
            $options[Mysql::ATTR_SSL_CA] = true;
        } elseif ($dbConfig->sslCa) {
            // ssl_ca = string path to CA file
            $options[Mysql::ATTR_SSL_CA] = $dbConfig->sslCa;
        }

        if ($options) {
            // available only with mysqlnd
            if (defined(Mysql::class . '::ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = $dbConfig->sslVerifyServerCert;
            }
        }

        return $options;
    }

    /** @throws SqlException */
    private function setMultiRecordQuery(string $verb, bool $onDuplicateKeyUpdate = false): static
    {
        $fields = [];

        foreach ($this->records as $record) {
            foreach ($record->values as $field => $value) {
                $fields[$field] = true;
            }
            foreach ($record->rawValues as $field => $value) {
                $fields[$field] = true;
            }
        }

        $fields = array_keys($fields);

        $rows = [];
        $params = [];

        foreach ($this->records as $record) {
            $row = [];

            foreach ($fields as $field) {
                if (isset($record->rawValues[$field])) {
                    $row[] = $record->rawValues[$field];

                    continue;
                }

                if (!isset($record->values[$field]) && !array_key_exists($field, $this->values)) {
                    $row[] = 'DEFAULT';

                    continue;
                }

                $row[] = '?';
                $params[] = $record->values[$field];
            }

            $rows[] = '(' . implode(', ', $row) . ')';
        }

        $query = $verb . ' INTO ' . $this->escapeIdentifier(Type::notNull($this->table)) . "\n";
        $query .= '(' . implode(', ', array_map($this->escapeIdentifier(...), $fields)) . ")\n";
        $query .= "VALUES\n";
        $query .= implode(",\n", $rows);

        if ($onDuplicateKeyUpdate) {
            $query .= "\n" . $this->buildOnDuplicateKeyUpdate($fields);
        }

        return $this->setQuery($query, $params);
    }

    /** @param list<string> $fields */
    private function buildOnDuplicateKeyUpdate(array $fields): string
    {
        $updates = [];

        foreach ($fields as $field) {
            $field = $this->escapeIdentifier($field);
            $updates[] = "$field = VALUES($field)";
        }

        return 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }
}
