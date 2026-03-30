<?php
/**
 * Controlador de Estadísticas y Predicción
 */

class AnalyticsController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getPurchaseStatistics($product_id = null, $year = null) {
        try {
            $year = $year ?? date('Y');
            
            $query = "
                SELECT 
                    ps.*,
                    p.name,
                    p.sku,
                    ROUND(AVG(ps.total_quantity) OVER (PARTITION BY ps.product_id), 2) as avg_quantity
                FROM purchase_statistics ps
                JOIN products p ON ps.product_id = p.id
                WHERE ps.year = ?
            ";
            
            $params = [$year];
            
            if ($product_id) {
                $query .= " AND ps.product_id = ?";
                $params[] = $product_id;
            }
            
            $query .= " ORDER BY ps.month, p.name";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function generatePredictions() {
        try {
            // Obtener datos históricos
            $stmt = $this->pdo->prepare("
                SELECT 
                    product_id,
                    month,
                    total_quantity,
                    season,
                    year
                FROM purchase_statistics
                WHERE year >= ? 
                ORDER BY product_id, year DESC, month DESC
                LIMIT 100
            ");
            
            $stmt->execute([date('Y') - 2]);
            $historical = $stmt->fetchAll();
            
            // Agrupar por producto
            $product_data = [];
            foreach ($historical as $record) {
                if (!isset($product_data[$record['product_id']])) {
                    $product_data[$record['product_id']] = [];
                }
                $product_data[$record['product_id']][] = $record;
            }
            
            // Generar predicciones
            $predictions = [];
            foreach ($product_data as $product_id => $data) {
                $prediction = $this->calculatePrediction($product_id, $data);
                if ($prediction) {
                    $predictions[] = $prediction;
                }
            }
            
            return $predictions;
        } catch (Exception $e) {
            error_log("Error generando predicciones: " . $e->getMessage());
            return [];
        }
    }
    
    private function calculatePrediction($product_id, $historical_data) {
        if (empty($historical_data)) {
            return null;
        }
        
        // Calcular promedio de demanda
        $total = array_sum(array_column($historical_data, 'total_quantity'));
        $average = $total / count($historical_data);
        
        // Aplicar factor por temporada actual
        $current_season = $this->getSeason();
        $season_factor = 1.0;
        
        $season_data = array_filter($historical_data, function($d) use ($current_season) {
            return $d['season'] === $current_season;
        });
        
        if (!empty($season_data)) {
            $season_avg = array_sum(array_column($season_data, 'total_quantity')) / count($season_data);
            $season_factor = $season_avg / $average;
        }
        
        $predicted_demand = round($average * $season_factor);
        $confidence = min(99, 50 + (count($historical_data) * 5)); // Confianza basada en datos históricos
        
        // Guardar predicción
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_predictions 
            (product_id, prediction_date, predicted_demand, confidence_score, season, factors)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $factors = json_encode([
            'historical_average' => $average,
            'season_factor' => $season_factor,
            'data_points' => count($historical_data)
        ]);
        
        $stmt->execute([
            $product_id,
            date('Y-m-d'),
            $predicted_demand,
            $confidence,
            $current_season,
            $factors
        ]);
        
        return [
            'product_id' => $product_id,
            'predicted_demand' => $predicted_demand,
            'confidence' => $confidence,
            'season' => $current_season
        ];
    }
    
    public function getDashboardMetrics() {
        try {
            // Órdenes del mes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total, SUM(total_amount) as revenue
                FROM orders
                WHERE EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM NOW())
                AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW())
            ");
            $stmt->execute();
            $monthly_stats = $stmt->fetch();
            
            // Órdenes pendientes de pago
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as pending_payment, SUM(balance) as pending_amount
                FROM orders
                WHERE payment_status IN ('pending', 'partial')
            ");
            $stmt->execute();
            $payment_stats = $stmt->fetch();
            
            // Tareas pendientes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as pending_tasks
                FROM tasks
                WHERE status IN ('pending', 'in_progress')
            ");
            $stmt->execute();
            $task_stats = $stmt->fetch();
            
            // Top productos
            $stmt = $this->pdo->prepare("
                SELECT p.name, SUM(oi.quantity) as total_sold
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE EXTRACT(MONTH FROM oi.created_at) = EXTRACT(MONTH FROM NOW())
                GROUP BY p.id, p.name
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->execute();
            $top_products = $stmt->fetchAll();
            
            return [
                'monthly_orders' => $monthly_stats['total'] ?? 0,
                'monthly_revenue' => $monthly_stats['revenue'] ?? 0,
                'pending_payments' => $payment_stats['pending_payment'] ?? 0,
                'pending_amount' => $payment_stats['pending_amount'] ?? 0,
                'pending_tasks' => $task_stats['pending_tasks'] ?? 0,
                'top_products' => $top_products
            ];
        } catch (PDOException $e) {
            error_log("Error obteniendo métricas: " . $e->getMessage());
            return [];
        }
    }
    
    private function getSeason() {
        $month = date('m');
        if ($month >= 12 || $month <= 2) return 'Invierno';
        if ($month >= 3 && $month <= 5) return 'Primavera';
        if ($month >= 6 && $month <= 8) return 'Verano';
        return 'Otoño';
    }
}
?>
