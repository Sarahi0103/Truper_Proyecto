<?php
/**
 * Modelo de Estadísticas y Analytics - TRUPPER
 */

require_once __DIR__ . '/../config/database.php';

class Analytics {
    private $conn;

    public function __construct() {
        $this->conn = $GLOBALS['db'];
    }

    /**
     * Obtener estadísticas de compras por mes
     */
    public function getPurchaseStatsByMonth($months = 12) {
        $query = "SELECT 
                    DATE_FORMAT(o.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(oi.quantity) as total_items,
                    SUM(oi.subtotal) as total_spent
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                  GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
                  ORDER BY month DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $months);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Productos más comprados
     */
    public function getTopPurchasedProducts($limit = 10) {
        $query = "SELECT 
                    p.id, p.name, p.category, p.sku,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.subtotal) as total_cost,
                    COUNT(DISTINCT oi.order_id) as purchase_count
                  FROM products p
                  JOIN order_items oi ON p.id = oi.product_id
                  JOIN orders o ON oi.order_id = o.id
                  GROUP BY p.id
                  ORDER BY total_quantity DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Tendencias por temporada/mes
     */
    public function getSeasonalTrends() {
        $query = "SELECT 
                    MONTH(o.created_at) as month,
                    MONTHNAME(o.created_at) as month_name,
                    COUNT(DISTINCT o.id) as orders,
                    SUM(oi.quantity) as items_purchased,
                    AVG(oi.quantity) as avg_qty_per_item,
                    GROUP_CONCAT(DISTINCT p.category) as categories
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  JOIN products p ON oi.product_id = p.id
                  WHERE YEAR(o.created_at) = YEAR(NOW())
                  GROUP BY MONTH(o.created_at)
                  ORDER BY month ASC";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Predicción de demanda usando promedio móvil
     */
    public function demandForecast($product_id, $months = 3) {
        // Obtener datos históricos
        $query = "SELECT 
                    DATE_FORMAT(o.created_at, '%Y-%m') as month,
                    SUM(oi.quantity) as quantity
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  WHERE oi.product_id = ?
                  GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $months_back = $months * 3;
        $stmt->bind_param("ii", $product_id, $months_back);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular promedio móvil simple
        if (count($result) < 3) {
            return null;
        }
        
        $forecast = [];
        $values = array_column($result, 'quantity');
        $average = array_sum($values) / count($values);
        
        // Promedio móvil de 3 meses
        $moving_avg = 0;
        for ($i = 0; $i < 3 && $i < count($values); $i++) {
            $moving_avg += $values[$i];
        }
        $moving_avg /= 3;
        
        return [
            'product_id' => $product_id,
            'average_monthly' => round($average, 2),
            'moving_average_3m' => round($moving_avg, 2),
            'forecast' => round($moving_avg, 0)
        ];
    }

    /**
     * Análisis de ingresos vs costos
     */
    public function getFinancialAnalysis($start_date, $end_date) {
        $query = "SELECT 
                    o.id as order_id,
                    o.total as revenue,
                    SUM(oi.quantity * p.cost_price) as cost,
                    o.total - SUM(oi.quantity * p.cost_price) as profit,
                    ((o.total - SUM(oi.quantity * p.cost_price)) / o.total * 100) as profit_margin
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  JOIN products p ON oi.product_id = p.id
                  WHERE DATE(o.created_at) BETWEEN ? AND ?
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Total general de estadísticas
     */
    public function getSummary() {
        // Total de órdenes
        $query = "SELECT COUNT(*) as total FROM orders";
        $total_orders = $this->conn->query($query)->fetch_assoc()['total'];
        
        // Total de ventas
        $query = "SELECT SUM(total) as total FROM orders";
        $total_sales = $this->conn->query($query)->fetch_assoc()['total'] ?? 0;
        
        // Total de clientes
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'client'";
        $total_clients = $this->conn->query($query)->fetch_assoc()['total'];
        
        // Costo total de compras
        $query = "SELECT SUM(oi.quantity * p.cost_price) as total FROM order_items oi JOIN products p ON oi.product_id = p.id";
        $total_cost = $this->conn->query($query)->fetch_assoc()['total'] ?? 0;
        
        return [
            'total_orders' => $total_orders,
            'total_sales' => round($total_sales, 2),
            'total_clients' => $total_clients,
            'total_cost' => round($total_cost, 2),
            'profit' => round($total_sales - $total_cost, 2)
        ];
    }
}
?>
