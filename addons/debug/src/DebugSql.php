<?php

namespace Redaxo\Debug;

use Override;
use PDOStatement;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\Exception;
use Redaxo\Core\Util\Timer;

use function assert;
use function count;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @internal
 */
final class DebugSql extends Sql
{
    #[Override]
    public function setQuery(string $query, array $params = [], array $options = []): static
    {
        try {
            $timer = new Timer();
            parent::setQuery($query, $params, $options);

            // to prevent double entries, log only if no params are passed
            if (empty($params)) {
                DebugAddon::instance()->clockworkRequest
                    ->addDatabaseQuery($query, $params, $timer->getDelta(), ['connection' => $this->DBID] + Backtrace::capture());
            }
        } catch (Exception $e) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $file = $trace[0]['file'] ?? null;
            $line = $trace[0]['line'] ?? null;
            for ($i = 1; $i < count($trace); ++$i) {
                if (isset($trace[$i]['file']) && !str_contains($trace[$i]['file'], 'sql.php')) {
                    $file = $trace[$i]['file'];
                    $line = $trace[$i]['line'] ?? null;
                    break;
                }
            }
            DebugAddon::instance()->clockwork
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

        DebugAddon::instance()->clockworkRequest
            ->addDatabaseQuery($qry, $params, $timer->getDelta(), ['connection' => $this->DBID] + Backtrace::capture());

        return $this;
    }
}
