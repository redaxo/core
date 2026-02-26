<?php

/**
 * Cronjob Addon.
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 *
 * @package redaxo\cronjob
 */

class rex_cronjob_manager_sql
{
    private rex_sql $sql;

    private function __construct(
        private ?rex_cronjob_manager $manager = null,
    ) {
        $this->sql = rex_sql::factory();
    }

    /** @return self */
    public static function factory(?rex_cronjob_manager $manager = null)
    {
        return new self($manager);
    }

    /** @return rex_cronjob_manager */
    public function getManager()
    {
        if (!is_object($this->manager)) {
            $this->manager = rex_cronjob_manager::factory();
        }
        return $this->manager;
    }

    /** @return bool */
    public function hasManager()
    {
        return is_object($this->manager);
    }

    /**
     * @param string $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->getManager()->setMessage($message);
    }

    /** @return string */
    public function getMessage()
    {
        return $this->getManager()->getMessage();
    }

    /** @return bool */
    public function hasMessage()
    {
        return $this->getManager()->hasMessage();
    }

    /**
     * @param int $id
     *
     * @throws rex_exception
     * @return string
     */
    public function getName($id)
    {
        $this->sql->setQuery('
            SELECT  name
            FROM    ' . rex::getTable('cronjob') . '
            WHERE   id = ?
            LIMIT   1
        ', [$id]);
        if (1 == $this->sql->getRows()) {
            return $this->sql->getValue('name');
        }
        throw new rex_exception(sprintf('No cronjob found with id %s', $id));
    }

    /**
     * @param int $id
     * @return bool
     */
    public function setStatus($id, $status)
    {
        $this->sql->setTable(rex::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setValue('status', $status);
        $this->sql->addGlobalUpdateFields();
        try {
            $this->sql->update();
            $success = true;
        } catch (rex_sql_exception) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function setExecutionStart($id, $reset = false)
    {
        $this->sql->setTable(rex::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        $this->sql->setDateTimeValue('execution_start', $reset ? 0 : time());
        try {
            $this->sql->update();
            return true;
        } catch (rex_sql_exception) {
            return false;
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->sql->setTable(rex::getTable('cronjob'));
        $this->sql->setWhere(['id' => $id]);
        try {
            $this->sql->delete();
            $success = true;
        } catch (rex_sql_exception) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    /**
     * @param callable(string,bool,string):void|null $callback Callback is called after every job execution (params: job name, success status, message)
     * @return void
     */
    public function check(?callable $callback = null)
    {
        $env = rex_cronjob_manager::getCurrentEnvironment();
        $script = 'script' === $env;

        $sql = rex_sql::factory();
        // $sql->setDebug();

        $query = '
            SELECT    id, name, type, parameters, `interval`, execution_moment
            FROM      ' . rex::getTable('cronjob') . '
            WHERE     status = 1
                AND   execution_start < ?
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

        $jobs = $sql->getArray($query, [rex_sql::datetime(time() - $minExecutionStartDiff), '%|' . $env . '|%', rex_sql::datetime()]);

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

                $manager = $this->getManager();
                $manager->setCronjob(rex_cronjob::factory($type));
                $manager->log(false, 0 != connection_status() ? 'Timeout' : 'Unknown error');
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
                    $callback($job['name'], $success, $this->getMessage());
                }

                $job['finished'] = true;
            }
            return;
        }

        rex_extension::register('RESPONSE_SHUTDOWN', function () use (&$jobs, $callback) {
            $jobs[0]['started'] = true;
            $success = $this->tryExecuteJob($jobs[0], true, true);

            if ($callback) {
                $callback($jobs[0]['name'], $success, $this->getMessage());
            }

            $jobs[0]['finished'] = true;
        });
    }

    /**
     * @param int $id
     * @param bool $log
     * @return bool
     */
    public function tryExecute($id, $log = true)
    {
        $sql = rex_sql::factory();
        $jobs = $sql->getArray('
            SELECT    id, name, type, parameters, `interval`
            FROM      ' . rex::getTable('cronjob') . '
            WHERE     id = ? AND environment LIKE ?
            LIMIT     1
        ', [$id, '%|' . rex_cronjob_manager::getCurrentEnvironment() . '|%']);

        if (!$jobs) {
            $this->getManager()->setMessage('Cronjob not found in database');
            $this->saveNextTime();
            return false;
        }

        return $this->tryExecuteJob($jobs[0], $log);
    }

    /**
     * @param array{id: int, interval: string, name: string, parameters: ?string, type: class-string<rex_cronjob>} $job
     * @param bool $log
     * @param bool $resetExecutionStart
     * @return bool
     */
    private function tryExecuteJob(array $job, $log = true, $resetExecutionStart = false)
    {
        $params = $job['parameters'] ? json_decode($job['parameters'], true) : [];

        /** @psalm-taint-escape callable */ // It is intended that the class name is coming from database
        $type = $job['type'];

        $cronjob = rex_cronjob::factory($type);

        $this->setNextTime($job['id'], $job['interval'], $resetExecutionStart);

        return $this->getManager()->tryExecute($cronjob, $job['name'], $params, $log, $job['id']);
    }

    /**
     * @param int $id
     * @param string $interval
     * @param bool $resetExecutionStart
     *
     * @return bool
     */
    public function setNextTime($id, $interval, $resetExecutionStart = false)
    {
        $nexttime = self::calculateNextTime(json_decode($interval, true));
        $nexttime = $nexttime ? rex_sql::datetime($nexttime) : null;
        $add = $resetExecutionStart ? ', execution_start = 0' : '';
        try {
            $this->sql->setQuery('
                UPDATE  ' . rex::getTable('cronjob') . '
                SET     nexttime = ?' . $add . '
                WHERE   id = ?
            ', [$nexttime, $id]);
            $success = true;
        } catch (rex_sql_exception) {
            $success = false;
        }
        $this->saveNextTime();
        return $success;
    }

    /** @return int|null */
    public function getMinNextTime()
    {
        $this->sql->setQuery('
            SELECT  MIN(nexttime) AS nexttime
            FROM    ' . rex::getTable('cronjob') . '
            WHERE   status = 1
        ');

        if (1 == $this->sql->getRows()) {
            return (int) $this->sql->getDateTimeValue('nexttime');
        }
        return null;
    }

    /**
     * @param int|null $nexttime
     * @return true
     */
    public function saveNextTime($nexttime = null)
    {
        if (null === $nexttime) {
            $nexttime = $this->getMinNextTime();
        }
        if (null === $nexttime) {
            $nexttime = 0;
        } else {
            $nexttime = max(1, $nexttime);
        }

        rex_config::set('cronjob', 'nexttime', $nexttime);
        return true;
    }

    /** @return int|null */
    public static function calculateNextTime(array $interval)
    {
        if (empty($interval['minutes']) || empty($interval['hours']) || empty($interval['days']) || empty($interval['weekdays']) || empty($interval['months'])) {
            return null;
        }

        $date = new DateTime('+5 min');
        $date->setTime((int) $date->format('G'), (int) floor((int) $date->format('i') / 5) * 5, 0);

        $isValid = static function ($value, $current) {
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
