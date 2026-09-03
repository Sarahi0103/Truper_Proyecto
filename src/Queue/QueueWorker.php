<?php
/**
 * Queue Worker
 * Ejecutor en segundo plano para procesar tareas asíncronas
 * Truper Platform
 */

require_once __DIR__ . '/QueueSystem.php';

class QueueWorker {
    private $pdo;
    private $queue;
    private $isDaemon;
    private $maxJobs;
    private $sleepSeconds;

    public function __construct($pdo, $isDaemon = false, $maxJobs = 50, $sleepSeconds = 3) {
        $this->pdo = $pdo;
        $this->queue = new QueueSystem($pdo);
        $this->isDaemon = $isDaemon;
        $this->maxJobs = $maxJobs;
        $this->sleepSeconds = $sleepSeconds;
    }

    /**
     * Inicia el procesamiento de trabajos
     */
    public function run() {
        $processed = 0;
        $startTime = time();

        echo "[" . date('Y-m-d H:i:s') . "] 🚀 Worker de colas iniciado...\n";

        do {
            $job = $this->queue->getNextJob();

            if ($job) {
                $this->processJob($job);
                $processed++;
                if (!$this->isDaemon && $processed >= $this->maxJobs) {
                    break;
                }
            } else {
                if ($this->isDaemon) {
                    sleep($this->sleepSeconds);
                } else {
                    break;
                }
            }

            // Evitar consumo excesivo de memoria en ejecuciones prolongadas
            if (memory_get_usage(true) > 128 * 1024 * 1024) {
                echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Límite de memoria alcanzado. Reiniciando worker...\n";
                break;
            }

        } while ($this->isDaemon);

        $duration = time() - $startTime;
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Worker finalizado. Trabajos procesados: {$processed} en {$duration}s.\n";
        return $processed;
    }

    /**
     * Procesa un trabajo individual
     */
    private function processJob($job) {
        $jobId = $job['id'];
        $jobType = $job['job'];
        $payload = json_decode($job['data'] ?? '[]', true) ?: [];

        echo "[" . date('Y-m-d H:i:s') . "] ⏳ Procesando Job #{$jobId}: {$jobType}...\n";
        $this->queue->markAsStarted($jobId);

        try {
            switch ($jobType) {
                case 'send_email':
                case 'SendEmailJob':
                    $this->handleEmailJob($payload);
                    break;

                case 'send_whatsapp':
                case 'SendWhatsAppJob':
                    $this->handleWhatsAppJob($payload);
                    break;

                case 'cleanup_stock_reservations':
                    $this->handleStockCleanup();
                    break;

                case 'sync_analytics':
                    $this->handleAnalyticsSync();
                    break;

                default:
                    // Si existe una clase para el job con método handle()
                    if (class_exists($jobType)) {
                        $instance = new $jobType($this->pdo, $payload);
                        if (method_exists($instance, 'handle')) {
                            $instance->handle();
                        }
                    } else {
                        throw new Exception("Tipo de trabajo no reconocido: {$jobType}");
                    }
                    break;
            }

            $this->queue->markAsCompleted($jobId);
            echo "[" . date('Y-m-d H:i:s') . "] 🟢 Job #{$jobId} completado con éxito.\n";
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] 🔴 Error en Job #{$jobId}: " . $e->getMessage() . "\n";
            $this->queue->markAsFailed($jobId, $e->getMessage());
        }
    }

    private function handleEmailJob($payload) {
        if (file_exists(__DIR__ . '/../Services/EmailService.php')) {
            require_once __DIR__ . '/../Services/EmailService.php';
            $service = new EmailService($this->pdo);
            $service->send(
                $payload['to'] ?? '',
                $payload['subject'] ?? '',
                $payload['body'] ?? '',
                $payload['is_html'] ?? true
            );
        }
    }

    private function handleWhatsAppJob($payload) {
        if (file_exists(__DIR__ . '/../Services/WhatsAppService.php')) {
            require_once __DIR__ . '/../Services/WhatsAppService.php';
            $service = new WhatsAppService($this->pdo);
            $service->sendMessage(
                $payload['phone'] ?? '',
                $payload['message'] ?? ''
            );
        }
    }

    private function handleStockCleanup() {
        if (file_exists(__DIR__ . '/../Services/StockReservationService.php')) {
            require_once __DIR__ . '/../Services/StockReservationService.php';
            $service = new StockReservationService($this->pdo);
            $service->cleanupExpiredReservations();
        }
    }

    private function handleAnalyticsSync() {
        if (file_exists(__DIR__ . '/../Services/AnalyticsCacheService.php')) {
            require_once __DIR__ . '/../Services/AnalyticsCacheService.php';
            $service = new AnalyticsCacheService($this->pdo);
            $service->refreshAll();
        }
    }
}
