<?php
/**
 * API de Analytics para Administrador
 * Proporciona métricas de ventas, productos, clientes
 */

require_once '../../config/config.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verificar sesión y rol
require_login();
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'employee'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$period = $_GET['period'] ?? 30; // días

try {
    // Calcular fechas
    $startDate = date('Y-m-d', strtotime("-{$period} days"));
    $previousStartDate = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));
    $previousEndDate = $startDate;
    
    // KPIs
    $kpis = [
        'total_sales' => 0,
        'total_orders' => 0,
        'avg_ticket' => 0,
        'conversion_rate' => 0,
        'sales_change' => 0,
        'orders_change' => 0,
        'ticket_change' => 0,
        'conversion_change' => 0
    ];
    
    // Ventas totales del período actual
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_sales,
               COUNT(*) as total_orders
        FROM sales_tickets
        WHERE issued_date >= ? AND deleted_at IS NULL
    ");
    $stmt->execute([$startDate]);
    $currentPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ventas totales del período anterior
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_sales,
               COUNT(*) as total_orders
        FROM sales_tickets
        WHERE issued_date >= ? AND issued_date < ? AND deleted_at IS NULL
    ");
    $stmt->execute([$previousStartDate, $previousEndDate]);
    $previousPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $kpis['total_sales'] = (float)$currentPeriod['total_sales'];
    $kpis['total_orders'] = (int)$currentPeriod['total_orders'];
    $kpis['avg_ticket'] = $currentPeriod['total_orders'] > 0 
        ? $kpis['total_sales'] / $currentPeriod['total_orders'] 
        : 0;
    
    // Calcular cambios porcentuales
    if ($previousPeriod['total_sales'] > 0) {
        $kpis['sales_change'] = (($kpis['total_sales'] - $previousPeriod['total_sales']) / $previousPeriod['total_sales']) * 100;
    }
    
    if ($previousPeriod['total_orders'] > 0) {
        $kpis['orders_change'] = (($kpis['total_orders'] - $previousPeriod['total_orders']) / $previousPeriod['total_orders']) * 100;
    }
    
    $previousAvgTicket = $previousPeriod['total_orders'] > 0 
        ? $previousPeriod['total_sales'] / $previousPeriod['total_orders'] 
        : 0;
    
    if ($previousAvgTicket > 0) {
        $kpis['ticket_change'] = (($kpis['avg_ticket'] - $previousAvgTicket) / $previousAvgTicket) * 100;
    }
    
    // Tasa de conversión (pedidos / visitas - simplificado)
    // En un sistema real, necesitarías tracking de visitas
    $kpis['conversion_rate'] = 2.5; // Valor de ejemplo
    
    // Gráfico de ventas por día
    $salesChart = [
        'labels' => [],
        'data' => []
    ];
    
    for ($i = $period - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $salesChart['labels'][] = date('d/m', strtotime($date));
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as daily_sales
            FROM sales_tickets
            WHERE DATE(issued_date) = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$date]);
        $dailySales = $stmt->fetch(PDO::FETCH_ASSOC);
        $salesChart['data'][] = (float)$dailySales['daily_sales'];
    }
    
    // Gráfico de pedidos por estado
    $ordersChart = [
        'labels' => [],
        'data' => []
    ];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(order_status, 'pending') as status, COUNT(*) as count
        FROM sales_tickets
        WHERE issued_date >= ? AND deleted_at IS NULL
        GROUP BY COALESCE(order_status, 'pending')
        ORDER BY count DESC
    ");
    $stmt->execute([$startDate]);
    $orderStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusLabels = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'processing' => 'En Proceso',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado'
    ];
    
    foreach ($orderStatuses as $status) {
        $ordersChart['labels'][] = $statusLabels[$status['status']] ?? $status['status'];
        $ordersChart['data'][] = (int)$status['count'];
    }
    
    // Productos más vendidos
    $stmt = $pdo->prepare("
        SELECT p.name as product_name, p.sku,
               SUM(oi.quantity) as units_sold,
               SUM(oi.line_total) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN sales_tickets st ON o.id = st.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE st.issued_date >= ? AND st.deleted_at IS NULL
        GROUP BY p.id, p.name, p.sku
        ORDER BY units_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mejores clientes
    $stmt = $pdo->prepare("
        SELECT c.company_name as customer_name,
               COUNT(DISTINCT o.id) as order_count,
               SUM(o.total_amount) as total_spent,
               MAX(o.order_date) as last_order
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        JOIN sales_tickets st ON o.id = st.order_id
        WHERE st.issued_date >= ? AND st.deleted_at IS NULL
        GROUP BY c.id, c.company_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate]);
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'kpis' => $kpis,
        'sales_chart' => $salesChart,
        'orders_chart' => $ordersChart,
        'top_products' => $topProducts,
        'top_customers' => $topCustomers
    ]);
    
} catch (Exception $e) {
    AppLogger::error("Analytics API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
