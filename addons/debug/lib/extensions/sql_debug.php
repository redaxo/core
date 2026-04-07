<?php

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\Exception;
use Redaxo\Core\Util\Timer;

/**
 * @internal
 */
class rex_sql_debug extends Sql
{
    #[Override]
    public function setQuery(string $query, array $params = [], array $options = []): static
    {
        try {
            $timer = new Timer();
            parent::setQuery($query, $params, $options);

            // to prevent double entries, log only if no params are passed
            if (empty($params)) {
                rex_debug_clockwork::getInstance()->getRequest()
                    ->addDatabaseQuery($query, $params, $timer->getDelta(), ['connection' => $this->DBID] + rex_debug::getTrace());
            }
        } catch (Exception $e) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $file = $trace[0]['file'];
            $line = $trace[0]['line'];
            for ($i = 1; $i < count($trace); ++$i) {
                if (isset($trace[$i]['file']) && !str_contains($trace[$i]['file'], 'sql.php')) {
                    $file = $trace[$i]['file'];
                    $line = $trace[$i]['line'];
                    break;
                }
            }
            rex_debug_clockwork::getInstance()
                ->log('error', $e->getMessage(), ['file' => $file, 'line' => $line]);
            throw $e; // re-throw exception after logging
        }

        return $this;
    }

    #[Override]
    public function execute(array $params = [], array $options = []): static
    {
        assert($this->stmt instanceof PDOStatement);
        $qry = $this->stmt->queryString;

        $timer = new Timer();
        parent::execute($params, $options);

        rex_debug_clockwork::getInstance()->getRequest()
            ->addDatabaseQuery($qry, $params, $timer->getDelta(), ['connection' => $this->DBID] + rex_debug::getTrace());

        return $this;
    }
}
