<?php

namespace Redaxo\Core\Cronjob;

use DateTime;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\Type\AbstractType;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;

use function in_array;
use function ini_get;
use function is_array;
use function is_object;
use function sprintf;

/** @internal */
final class CronjobManager
{
    private readonly Sql $sql;

    private function __construct(
        private ?CronjobExecutor $executor = null,
    ) {
        $this->sql = Sql::factory();
    }

    public static function factory(?CronjobExecutor $executor = null): self
    {
        return new self($executor);
    }

    public function getExecutor(): CronjobExecutor
    {
        if (!is_object($this->executor)) {
            $this->executor = CronjobExecutor::factory();
        }
        return $this->executor;
    }

    public function hasExecutor(): bool
    {
        return is_object($this->executor);
    }

    public function getMessage(): string
    {
        return $this->getExecutor()->message;
    }

    public function getName(int $id): string
    {
        $this->sql->setQuery('
            SELECT  name
            FROM    ' . Core::getTable('cronjob') . '
            WHERE   id = ?
            LIMIT   1
        ', [$id]);
        if (1 == $this->sql->getRows()) {
            return $this->sql->getValue('name');
        }
        throw new RuntimeException(sprintf('No cronjob found with id %s.', $id));
    }

    public function setStatus(int $id, int $status): void
    {
        $this->sql->setTable(Core::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setValue('status', $status);
        $this->sql->addGlobalUpdateFields();
        $this->sql->update();
        $this->saveNextTime();
    }

    public function setExecutionStart(int $id, bool $reset = false): void
    {
        $this->sql->setTable(Core::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setDateTimeValue('execution_start', $reset ? null : time());
        $this->sql->update();
    }

    public function delete(int $id): void
    {
        $this->sql->setTable(Core::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        $this->sql->delete();
        $this->saveNextTime();
    }

    /** @param callable(string,bool,string):void|null $callback Callback is called after every job execution (params: job name, success status, message) */
    public function check(?callable $callback = null): void
    {
        $env = CronjobExecutor::getCurrentEnvironment();
        $script = 'script' === $env;

        $sql = Sql::factory();
        // $sql->setDebug();

        $query = '
            SELECT    id, name, type, parameters, `interval`, execution_moment
            FROM      ' . Core::getTable('cronjob') . '
            WHERE     status = 1
                AND   execution_start IS NULL OR execution_start < ?
                AND   environment LIKE ?
                AND   nexttime <= ?
            ORDER BY  nexttime ASC, execution_moment DESC, name ASC
        ';

        if ($script) {
            $minExecutionStartDiff = 6 * 60 * 60;
        } else {
            $query .= ' LIMIT 1';

            $minExecutionStartDiff = 2 * ((int) ini_get('max_execution_time') ?: 60 * 60);
        }

        $jobs = $sql->getArray($query, [Sql::datetime(time() - $minExecutionStartDiff), '%|' . $env . '|%', Sql::datetime()]);

        if (!$jobs) {
            $this->saveNextTime();
            return;
        }

        ignore_user_abort(true);
        register_shutdown_function(function () use (&$jobs): void {
            foreach ($jobs as $job) {
                if (isset($job['finished'])) {
                    continue;
                }

                if (!isset($job['started'])) {
                    $this->setExecutionStart($job['id'], true);
                    continue;
                }

                /** @psalm-taint-escape callable */ // It is intended that the class name is coming from database
                $type = $job['type'];

                $executor = $this->getExecutor();
                $executor->setCronjob(AbstractType::factory($type));
                $executor->log(false, 0 != connection_status() ? 'Timeout' : 'Unknown error');
                $this->setNextTime($job['id'], $job['interval'], true);
            }

            $this->saveNextTime();
        });

        foreach ($jobs as $job) {
            $this->setExecutionStart($job['id']);
        }

        if ($script || 1 == $jobs[0]['execution_moment']) {
            foreach ($jobs as &$job) {
                $job['started'] = true;
                $success = $this->tryExecuteJob($job, true, true);

                if ($callback) {
                    $callback($job['name'], $success, $this->getExecutor()->message);
                }

                $job['finished'] = true;
            }
            return;
        }

        Extension::register('RESPONSE_SHUTDOWN', function () use (&$jobs, $callback) {
            $jobs[0]['started'] = true;
            $success = $this->tryExecuteJob($jobs[0], true, true);

            if ($callback) {
                $callback($jobs[0]['name'], $success, $this->getExecutor()->message);
            }

            $jobs[0]['finished'] = true;
        });
    }

    public function tryExecute(int $id, bool $log = true): bool
    {
        $sql = Sql::factory();
        $jobs = $sql->getArray('
            SELECT    id, name, type, parameters, `interval`
            FROM      ' . Core::getTable('cronjob') . '
            WHERE     id = ? AND environment LIKE ?
            LIMIT     1
        ', [$id, '%|' . CronjobExecutor::getCurrentEnvironment() . '|%']);

        if (!$jobs) {
            $this->getExecutor()->message = 'Cronjob not found in database';
            $this->saveNextTime();
            return false;
        }

        return $this->tryExecuteJob($jobs[0], $log);
    }

    /** @param array{id: int, interval: string, name: string, parameters: ?string, type: class-string<AbstractType>} $job */
    private function tryExecuteJob(array $job, bool $log = true, bool $resetExecutionStart = false): bool
    {
        /** @var array<string, mixed> $params */
        $params = $job['parameters'] ? json_decode($job['parameters'], true) : [];
        if (!is_array($params)) {
            $params = [];
        }

        /** @psalm-taint-escape callable */ // It is intended that the class name is coming from database
        $type = $job['type'];

        $cronjob = AbstractType::factory($type);

        $this->setNextTime($job['id'], $job['interval'], $resetExecutionStart);

        return $this->getExecutor()->tryExecute($cronjob, $job['name'], $params, $log, $job['id']);
    }

    public function setNextTime(int $id, string $interval, bool $resetExecutionStart = false): void
    {
        $nexttime = self::calculateNextTime(json_decode($interval, true));
        $nexttime = $nexttime ? Sql::datetime($nexttime) : null;
        $add = $resetExecutionStart ? ', execution_start = NULL' : '';
        $this->sql->setQuery('
            UPDATE  ' . Core::getTable('cronjob') . '
            SET     nexttime = ?' . $add . '
            WHERE   id = ?
        ', [$nexttime, $id]);
        $this->saveNextTime();
    }

    public function getMinNextTime(): ?int
    {
        $this->sql->setQuery('
            SELECT  MIN(nexttime) AS nexttime
            FROM    ' . Core::getTable('cronjob') . '
            WHERE   status = 1
        ');

        if (1 == $this->sql->getRows()) {
            return (int) $this->sql->getDateTimeValue('nexttime');
        }
        return null;
    }

    /** @return true */
    public function saveNextTime(?int $nexttime = null): bool
    {
        if (null === $nexttime) {
            $nexttime = $this->getMinNextTime();
        }
        if (null === $nexttime) {
            $nexttime = 0;
        } else {
            $nexttime = max(1, $nexttime);
        }

        Core::setConfig('cronjob_nexttime', $nexttime);
        return true;
    }

    /** @param array<string, 'all'|list<int>> $interval */
    public static function calculateNextTime(array $interval): ?int
    {
        if (empty($interval['minutes']) || empty($interval['hours']) || empty($interval['days']) || empty($interval['weekdays']) || empty($interval['months'])) {
            return null;
        }

        $date = new DateTime('+5 min');
        $date->setTime((int) $date->format('G'), (int) floor((int) $date->format('i') / 5) * 5, 0);

        $isValid = static function ($value, $current): bool {
            return 'all' === $value || in_array($current, $value);
        };

        $validateTime = static function () use ($interval, $date, $isValid) {
            while (!$isValid($interval['hours'], $date->format('G'))) {
                $date->modify('+1 hour');
                $date->setTime((int) $date->format('G'), 0, 0);
            }

            while (!$isValid($interval['minutes'], (int) $date->format('i'))) {
                $date->modify('+5 min');

                while (!$isValid($interval['hours'], $date->format('G'))) {
                    $date->modify('+1 hour');
                    $date->setTime((int) $date->format('G'), 0, 0);
                }
            }
        };

        $validateTime();

        if (
            !$isValid($interval['days'], $date->format('j'))
            || !$isValid($interval['weekdays'], $date->format('w'))
            || !$isValid($interval['months'], $date->format('n'))
        ) {
            $date->setTime(0, 0, 0);
            $validateTime();

            while (!$isValid($interval['months'], $date->format('n'))) {
                $date->modify('first day of next month');
            }

            while (!$isValid($interval['days'], $date->format('j')) || !$isValid($interval['weekdays'], $date->format('w'))) {
                $date->modify('+1 day');

                while (!$isValid($interval['months'], $date->format('n'))) {
                    $date->modify('first day of next month');
                }
            }
        }

        return $date->getTimestamp();
    }
}
