<?php

use Redaxo\Core\Database\Util;
use Redaxo\Core\Http\Exception\NotFoundHttpException;

$logFile = Util::slowQueryLogPath();

if (null === $logFile) {
    throw new NotFoundHttpException('Slow query log file not found.');
}

require_once __DIR__ . '/log.external.php';
