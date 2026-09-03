<?php
/**
 * API de Reportes Avanzados
 * Exportación de reportes en Excel/PDF, análisis de inventario, reportes fiscales
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

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'sales':
            // Reporte de ventas
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');
            $format = $_GET['format'] ?? 'json';
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(issued_date) as date,
                    COUNT(*) as orders_count,
                    SUM(total_amount) as total_sales,
                    AVG(total_amount) as avg_order_value
                FROM sales_tickets
                WHERE issued_date BETWEEN ? AND ?
                AND deleted_at IS NULL
                GROUP BY DATE(issued_date)
                ORDER BY date ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="sales_report.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Fecha', 'Pedidos', 'Ventas Totales', 'Ticket Promedio']);
                
                foreach ($salesData as $row) {
                    fputcsv($output, [
                        $row['date'],
                        $row['orders_count'],
                        $row['total_sales'],
                        $row['avg_order_value']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $salesData]);
            break;
            
        case 'inventory':
            // Reporte de inventario
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $format = $_GET['format'] ?? 'json';
            
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.sku,
                    p.name,
                    p.category_id,
                    c.name as category_name,
                    COALESCE(p.stock_online, p.stock_quantity) as current_stock,
                    p.price,
                    p.cost,
                    (COALESCE(p.stock_online, p.stock_quantity) * p.price) as total_value,
                    p.low_stock_threshold
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_online_visible = true
                ORDER BY p.name ASC
            ");
            $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="inventory_report.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['SKU', 'Nombre', 'Categoría', 'Stock', 'Precio', 'Costo', 'Valor Total', 'Umbral Mínimo']);
                
                foreach ($inventoryData as $row) {
                    fputcsv($output, [
                        $row['sku'],
                        $row['name'],
                        $row['category_name'],
                        $row['current_stock'],
                        $row['price'],
                        $row['cost'],
                        $row['total_value'],
                        $row['low_stock_threshold']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $inventoryData]);
            break;
            
        case 'fiscal':
            // Reporte fiscal
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');
            $format = $_GET['format'] ?? 'json';
            
            $stmt = $pdo->prepare("
                SELECT 
                    st.folio,
                    st.customer_name,
                    st.tax_regime_selected,
                    st.cfdi_use,
                    st.total_amount,
                    st.tax_amount,
                    st.subtotal,
                    st.issued_date,
                    st.invoice_required
                FROM sales_tickets st
                WHERE st.issued_date BETWEEN ? AND ?
                AND st.deleted_at IS NULL
                ORDER BY st.issued_date ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $fiscalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="fiscal_report.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Folio', 'Cliente', 'Régimen Fiscal', 'Uso CFDI', 'Total', 'IVA', 'Subtotal', 'Fecha', 'Requiere Factura']);
                
                foreach ($fiscalData as $row) {
                    fputcsv($output, [
                        $row['folio'],
                        $row['customer_name'],
                        $row['tax_regime_selected'],
                        $row['cfdi_use'],
                        $row['total_amount'],
                        $row['tax_amount'],
                        $row['subtotal'],
                        $row['issued_date'],
                        $row['invoice_required'] ? 'Sí' : 'No'
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $fiscalData]);
            break;
            
        case 'customers':
            // Reporte de clientes
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $format = $_GET['format'] ?? 'json';
            
            $stmt = $pdo->query("
                SELECT 
                    c.id,
                    c.company_name,
                    c.rfc,
                    c.email,
                    c.phone,
                    COUNT(DISTINCT o.id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as total_spent,
                    MAX(o.order_date) as last_order_date
                FROM clients c
                LEFT JOIN orders o ON c.id = o.client_id
                GROUP BY c.id, c.company_name, c.rfc, c.email, c.phone
                ORDER BY total_spent DESC
            ");
            $customersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="customers_report.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Empresa', 'RFC', 'Email', 'Teléfono', 'Pedidos Totales', 'Total Gastado', 'Última Compra']);
                
                foreach ($customersData as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['company_name'],
                        $row['rfc'],
                        $row['email'],
                        $row['phone'],
                        $row['total_orders'],
                        $row['total_spent'],
                        $row['last_order_date']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $customersData]);
            break;
            
        case 'profitability':
            // Reporte de rentabilidad
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.sku,
                    p.name,
                    p.price,
                    p.cost,
                    (p.price - p.cost) as profit_per_unit,
                    COUNT(oi.id) as units_sold,
                    COUNT(oi.id) * (p.price - p.cost) as total_profit,
                    (COUNT(oi.id) * p.price) as total_revenue
                FROM products p
                JOIN order_items oi ON p.id = oi.product_id
                JOIN orders o ON oi.order_id = o.id
                JOIN sales_tickets st ON o.id = st.order_id
                WHERE st.issued_date BETWEEN ? AND ?
                AND st.deleted_at IS NULL
                GROUP BY p.id, p.sku, p.name, p.price, p.cost
                ORDER BY total_profit DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $profitabilityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $profitabilityData]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Reports API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
