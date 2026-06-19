<?php
/**
 * Queue System Básico
 * Sistema de colas para procesar tareas en background
 */

class QueueSystem {
    private $pdo;
    private $table = 'jobs';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id SERIAL PRIMARY KEY,
                    job VARCHAR(255) NOT NULL,
                    data TEXT,
                    status VARCHAR(50) DEFAULT 'pending',
                    attempts INTEGER DEFAULT 0,
                    max_attempts INTEGER DEFAULT 3,
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    started_at TIMESTAMP,
                    completed_at TIMESTAMP
                )
            ");

            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_status ON {$this->table}(status)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_jobs_created_at ON {$this->table}(created_at)");
        } catch (Exception $e) {
            error_log("Error creating jobs table: " . $e->getMessage());
        }
    }

    /**
     * Agrega un trabajo a la cola
     */
    public function dispatch($job, $data = null, $maxAttempts = 3) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (job, data, max_attempts, status)
                VALUES (?, ?, ?, 'pending') RETURNING id
            ");
            $stmt->execute([
                $job,
                $data ? json_encode($data) : null,
                $maxAttempts
            ]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error dispatching job: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el siguiente trabajo pendiente
     */
    public function getNextJob() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE status = 'pending'
                AND (attempts < max_attempts OR max_attempts IS NULL)
                ORDER BY created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting next job: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Marca trabajo como en proceso
     */
    public function markAsStarted($jobId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET status = 'processing', started_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$jobId]);
        } catch (Exception $e) {
            error_log("Error marking job as started: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca trabajo como completado
     */
    public function markAsCompleted($jobId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$jobId]);
        } catch (Exception $e) {
            error_log("Error marking job as completed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca trabajo como fallido
     */
    public function markAsFailed($jobId, $errorMessage = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET status = 'failed',
                    attempts = attempts + 1,
                    error_message = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$errorMessage, $jobId]);
        } catch (Exception $e) {
            error_log("Error marking job as failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Procesa trabajos pendientes
     */
    public function process($maxJobs = 10) {
        $processed = 0;

        for ($i = 0; $i < $maxJobs; $i++) {
            $job = $this->getNextJob();
            if (!$job) {
                break;
            }

            $this->markAsStarted($job['id']);

            try {
                $result = $this->executeJob($job);
                
                if ($result === true) {
                    $this->markAsCompleted($job['id']);
                    $processed++;
                } else {
                    $this->markAsFailed($job['id'], $result);
                }
            } catch (Exception $e) {
                $this->markAsFailed($job['id'], $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Ejecuta un trabajo específico
     */
    private function executeJob($job) {
        $data = json_decode($job['data'], true);
        
        switch ($job['job']) {
            case 'send_email':
                return $this->sendEmail($data);
                
            case 'sync_marketplace':
                return $this->syncMarketplace($data);
                
            case 'generate_report':
                return $this->generateReport($data);
                
            case 'cleanup_old_data':
                return $this->cleanupOldData($data);
                
            default:
                return "Unknown job type: {$job['job']}";
        }
    }

    /**
     * Envía email (placeholder)
     */
    private function sendEmail($data) {
        // Implementar lógica de envío de email
        error_log("Sending email to: " . ($data['to'] ?? 'unknown'));
        return true;
    }

    /**
     * Sincroniza con Marketplace (placeholder)
     */
    private function syncMarketplace($data) {
        // Implementar lógica de sincronización
        error_log("Syncing marketplace for SKU: " . ($data['sku'] ?? 'unknown'));
        return true;
    }

    /**
     * Genera reporte (placeholder)
     */
    private function generateReport($data) {
        // Implementar lógica de generación de reportes
        error_log("Generating report: " . ($data['type'] ?? 'unknown'));
        return true;
    }

    /**
     * Limpia datos antiguos (placeholder)
     */
    private function cleanupOldData($data) {
        // Implementar lógica de limpieza
        error_log("Cleaning up old data");
        return true;
    }

    /**
     * Obtiene estadísticas de la cola
     */
    public function getStats() {
        try {
            $stats = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0
            ];

            foreach (array_keys($stats) as $status) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = ?");
                $stmt->execute([$status]);
                $stats[$status] = (int)$stmt->fetchColumn();
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting queue stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpia trabajos completados antiguos
     */
    public function cleanup($daysOld = 7) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM {$this->table}
                WHERE status IN ('completed', 'failed')
                AND completed_at < NOW() - INTERVAL '{$daysOld} days'
            ");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error cleaning up queue: " . $e->getMessage());
            return false;
        }
    }
}
