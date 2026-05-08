<?php
/**
 * Modelo de Estadísticas y Analytics - Truper
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
                $stmt = $this->conn->prepare("SELECT TO_CHAR(o.created_at, 'YYYY-MM') as month, COUNT(DISTINCT o.id) as total_orders, SUM(oi.quantity) as total_items, SUM(oi.subtotal) as total_spent FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.created_at >= (NOW() - (:months || ' months')::interval) GROUP BY TO_CHAR(o.created_at, 'YYYY-MM') ORDER BY month DESC");
                $stmt->execute([':months' => $months]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Productos más comprados
     */
    public function getTopPurchasedProducts($limit = 10) {
                $stmt = $this->conn->prepare("SELECT p.id, p.name, p.category, p.sku, SUM(oi.quantity) as total_quantity, SUM(oi.subtotal) as total_cost, COUNT(DISTINCT oi.order_id) as purchase_count FROM products p JOIN order_items oi ON p.id = oi.product_id JOIN orders o ON oi.order_id = o.id GROUP BY p.id, p.name, p.category, p.sku ORDER BY total_quantity DESC LIMIT :limit");
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tendencias por temporada/mes
     */
    public function getSeasonalTrends() {
                $stmt = $this->conn->prepare("SELECT EXTRACT(MONTH FROM o.created_at)::int as month, TO_CHAR(o.created_at, 'TMMonth') as month_name, COUNT(DISTINCT o.id) as orders, SUM(oi.quantity) as items_purchased, AVG(oi.quantity) as avg_qty_per_item, STRING_AGG(DISTINCT p.category, ', ') as categories FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE EXTRACT(YEAR FROM o.created_at) = EXTRACT(YEAR FROM NOW()) GROUP BY EXTRACT(MONTH FROM o.created_at), TO_CHAR(o.created_at, 'TMMonth') ORDER BY month ASC");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Predicción de demanda usando promedio móvil
     */
    public function demandForecast($product_id, $months = 3) {
        // Obtener datos históricos
                $months_back = $months * 3;
                $stmt = $this->conn->prepare("SELECT TO_CHAR(o.created_at, 'YYYY-MM') as month, SUM(oi.quantity) as quantity FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.product_id = :product_id GROUP BY TO_CHAR(o.created_at, 'YYYY-MM') ORDER BY month DESC LIMIT :limit");
                $stmt->bindValue(':product_id', (int)$product_id, PDO::PARAM_INT);
                $stmt->bindValue(':limit', (int)$months_back, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
                $stmt = $this->conn->prepare("SELECT o.id as order_id, o.total_amount as revenue, SUM(oi.quantity * COALESCE(p.cost_price, p.unit_price, 0)) as cost, o.total_amount - SUM(oi.quantity * COALESCE(p.cost_price, p.unit_price, 0)) as profit, CASE WHEN o.total_amount = 0 THEN 0 ELSE ((o.total_amount - SUM(oi.quantity * COALESCE(p.cost_price, p.unit_price, 0))) / o.total_amount * 100) END as profit_margin FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date GROUP BY o.id, o.total_amount, o.created_at ORDER BY o.created_at DESC");
                $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total general de estadísticas
     */
    public function getSummary() {
        // Total de órdenes
        $total_orders = (int)$this->conn->query("SELECT COUNT(*) as total FROM orders")->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de ventas
        $total_sales = $this->conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Total de clientes
        $total_clients = (int)$this->conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'")->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Costo total de compras
        $total_cost = $this->conn->query("SELECT COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, p.unit_price, 0)), 0) as total FROM order_items oi JOIN products p ON oi.product_id = p.id")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
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


