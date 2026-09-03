<?php
/**
 * Queue Worker Test Suite
 * Truper Platform
 */

class QueueWorkerTest {
    public static function run() {
        global $pdo;

        try {
            require_once __DIR__ . '/../src/Queue/QueueSystem.php';
            require_once __DIR__ . '/../src/Queue/QueueWorker.php';

            $queue = new QueueSystem($pdo);

            // 1. Despachar un job de prueba
            $jobId = $queue->dispatch('cleanup_stock_reservations', ['timestamp' => time()]);
            if (!$jobId) {
                return [
                    'name' => 'Queue Dispatch Test',
                    'passed' => false,
                    'message' => 'Fallo al encolar trabajo en la base de datos'
                ];
            }

            // 2. Ejecutar el worker para procesar el job
            $worker = new QueueWorker($pdo, false, 5);
            ob_start();
            $processed = $worker->run();
            $log = ob_get_clean();

            if ($processed <= 0) {
                return [
                    'name' => 'Queue Processing Test',
                    'passed' => false,
                    'message' => 'El worker no procesó ningún trabajo. Log: ' . $log
                ];
            }

            return [
                'name' => 'Queue System & Asynchronous Worker Test',
                'passed' => true,
                'message' => "Trabajo #{$jobId} despachado y procesado exitosamente por el QueueWorker."
            ];
        } catch (Exception $e) {
            return [
                'name' => 'Queue Worker Test Suite',
                'passed' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
