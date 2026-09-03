<?php
/**
 * Stock Alert Service
 * Sistema de alertas de stock bajo para administradores
 */

class StockAlertService {
    private $pdo;
    private $logger;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AppLogger();
    }
    
    /**
     * Escanear productos y crear alertas automáticamente
     * 
     * @return array Resultado del escaneo
     */
    public function scanAndCreateAlerts() {
        try {
            $stmt = $this->pdo->prepare("SELECT scan_and_create_stock_alerts() as count");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = (int)($result['count'] ?? 0);
            
            $this->logger->info("Stock alerts scan completed: {$count} alerts created/updated");
            
            return [
                'success' => true,
                'alerts_count' => $count
            ];
        } catch (Exception $e) {
            $this->logger->error("Error scanning stock alerts: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al escanear alertas de stock'
            ];
        }
    }
    
    /**
     * Obtener alertas activas
     * 
     * @return array Alertas activas
     */
    public function getActiveAlerts() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM get_active_stock_alerts()");
            $stmt->execute();
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'alerts' => $alerts
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting active alerts: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener alertas activas'
            ];
        }
    }
    
    /**
     * Resolver alerta de stock
     * 
     * @param int $alertId ID de la alerta
     * @param int $resolvedBy ID del usuario que resuelve
     * @return array Resultado de la operación
     */
    public function resolveAlert($alertId, $resolvedBy) {
        try {
            $stmt = $this->pdo->prepare("SELECT resolve_stock_alert(?, ?) as resolved");
            $stmt->execute([$alertId, $resolvedBy]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $resolved = (bool)($result['resolved'] ?? false);
            
            if ($resolved) {
                $this->logger->info("Stock alert {$alertId} resolved by user {$resolvedBy}");
                return [
                    'success' => true,
                    'message' => 'Alerta resuelta correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No se pudo resolver la alerta'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error("Error resolving alert: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al resolver alerta'
            ];
        }
    }
    
    /**
     * Obtener estadísticas de alertas
     * 
     * @return array Estadísticas
     */
    public function getAlertStats() {
        try {
            $stats = [
                'total_active' => 0,
                'out_of_stock' => 0,
                'critical' => 0,
                'low_stock' => 0,
                'resolved_today' => 0
            ];
            
            // Total activas
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM stock_alerts WHERE is_resolved = FALSE");
            $stmt->execute();
            $stats['total_active'] = (int)$stmt->fetchColumn();
            
            // Por tipo
            $stmt = $this->pdo->prepare("
                SELECT alert_type, COUNT(*) 
                FROM stock_alerts 
                WHERE is_resolved = FALSE 
                GROUP BY alert_type
            ");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['alert_type']] = (int)$row['count'];
            }
            
            // Resueltas hoy
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM stock_alerts 
                WHERE is_resolved = TRUE 
                AND DATE(resolved_at) = CURRENT_DATE
            ");
            $stmt->execute();
            $stats['resolved_today'] = (int)$stmt->fetchColumn();
            
            return [
                'success' => true,
                'stats' => $stats
            ];
        } catch (Exception $e) {
            $this->logger->error("Error getting alert stats: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas'
            ];
        }
    }
    
    /**
     * Crear alerta manualmente
     * 
     * @param array $data Datos de la alerta
     * @return array Resultado de la operación
     */
    public function createManualAlert($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_alerts (product_id, product_sku, product_name, current_stock, low_stock_threshold, alert_type)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            
            $stmt->execute([
                $data['product_id'],
                $data['product_sku'],
                $data['product_name'],
                $data['current_stock'],
                $data['low_stock_threshold'],
                $data['alert_type'] ?? 'low_stock'
            ]);
            
            $alertId = $stmt->fetchColumn();
            
            $this->logger->info("Manual stock alert created: {$alertId}");
            
            return [
                'success' => true,
                'alert_id' => $alertId
            ];
        } catch (Exception $e) {
            $this->logger->error("Error creating manual alert: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error al crear alerta manual'
            ];
        }
    }
}
