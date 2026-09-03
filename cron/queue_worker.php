<?php
/**
 * CLI Cron Script para ejecutar el QueueWorker
 * Uso: php cron/queue_worker.php [--daemon]
 * Truper Platform
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Queue/QueueWorker.php';

$isDaemon = in_array('--daemon', $argv ?? []);
$worker = new QueueWorker($pdo, $isDaemon);
$worker->run();
