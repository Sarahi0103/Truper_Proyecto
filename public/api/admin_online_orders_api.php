<?php
/**
 * API de Administración de Pedidos en Línea
 * Maneja operaciones CRUD para pedidos online (sales_tickets)
 */

require_once '../../config/config.php';
require_once '../../src/utils/AppLogger.php';
require_once '../../src/Services/ShippingTrackingService.php';

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
        case 'list':
            // Listar pedidos en línea con filtros opcionales
            $statusFilter = $_GET['status'] ?? '';
            $dateFilter = $_GET['date'] ?? '';
            $searchFilter = $_GET['search'] ?? '';
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($statusFilter) {
                $where .= " AND order_status = ?";
                $params[] = $statusFilter;
            }
            
            if ($dateFilter) {
                $where .= " AND DATE(issued_date) = ?";
                $params[] = $dateFilter;
            }
            
            if ($searchFilter) {
                $where .= " AND (folio ILIKE ? OR customer_name ILIKE ?)";
                $params[] = "%{$searchFilter}%";
                $params[] = "%{$searchFilter}%";
            }
            
            $stmt = $pdo->prepare("
                SELECT id, folio, customer_name, total_amount, issued_date, order_status, 
                       payment_status, shipping_address_json, created_at
                FROM sales_tickets
                {$where}
                ORDER BY issued_date DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular estadísticas
            $stats = [
                'today_count' => 0,
                'pending_count' => 0,
                'month_sales' => 0,
                'to_deliver' => 0
            ];
            
            foreach ($orders as $order) {
                $orderDate = new DateTime($order['issued_date']);
                $today = new DateTime();
                
                if ($orderDate->format('Y-m-d') === $today->format('Y-m-d')) {
                    $stats['today_count']++;
                }
                
                if ($order['order_status'] === 'pending') {
                    $stats['pending_count']++;
                }
                
                if ($orderDate->format('Y-m') === $today->format('Y-m')) {
                    $stats['month_sales'] += (float)$order['total_amount'];
                }
                
                if (in_array($order['order_status'], ['confirmed', 'processing', 'shipped'])) {
                    $stats['to_deliver']++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'orders' => $orders,
                'stats' => $stats
            ]);
            break;
            
        case 'get':
            // Obtener detalles de un pedido específico
            $orderId = $_GET['id'] ?? 0;
            
            if (!$orderId) {
                echo json_encode(['success' => false, 'message' => 'ID de pedido requerido']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT st.*, 
                       json_agg(
                           json_build_object(
                               'product_name', p.name,
                               'quantity', sti.quantity,
                               'unit_price', sti.unit_price,
                               'subtotal', sti.subtotal
                           )
                       ) as items
                FROM sales_tickets st
                LEFT JOIN sales_ticket_items sti ON st.id = sti.sales_ticket_id
                LEFT JOIN products p ON sti.product_id = p.id
                WHERE st.id = ?
                GROUP BY st.id
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
                exit;
            }
            
            echo json_encode(['success' => true, 'order' => $order]);
            break;
            
        case 'update_status':
            // Actualizar estado de pedido
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['order_id'] ?? 0;
            $status = $input['status'] ?? '';
            
            $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if (!in_array($status, $allowedStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Estado no válido']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE sales_tickets 
                SET order_status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $orderId]);
            
            AppLogger::info("Order {$orderId} status updated to {$status} by user {$_SESSION['user_id']}");
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
            break;
            
        case 'update_payment_status':
            // Actualizar estado de pago
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['order_id'] ?? 0;
            $paymentStatus = $input['payment_status'] ?? '';
            
            $allowedPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
            
            if (!in_array($paymentStatus, $allowedPaymentStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Estado de pago no válido']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE sales_tickets 
                SET payment_status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$paymentStatus, $orderId]);
            
            echo json_encode(['success' => true, 'message' => 'Estado de pago actualizado']);
            break;
            
        case 'cancel':
            // Cancelar pedido
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['order_id'] ?? 0;
            $reason = $input['reason'] ?? '';
            
            // Verificar que el pedido no esté ya completado
            $stmt = $pdo->prepare("SELECT order_status FROM sales_tickets WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
                exit;
            }
            
            if (in_array($order['order_status'], ['delivered', 'cancelled'])) {
                echo json_encode(['success' => false, 'message' => 'No se puede cancelar este pedido']);
                exit;
            }
            
            // Cancelar reservas de stock si existen
            try {
                $stmt = $pdo->prepare("
                    UPDATE stock_reservations 
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE order_id = ? AND status = 'pending'
                ");
                $stmt->execute([$orderId]);
            } catch (Exception $e) {
                // Si la tabla no existe, continuar
            }
            
            // Actualizar estado del pedido
            $stmt = $pdo->prepare("
                UPDATE sales_tickets 
                SET order_status = 'cancelled', 
                    notes = COALESCE(notes, '') || ' | Cancelado: ' || ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $orderId]);
            
            AppLogger::info("Order {$orderId} cancelled by user {$_SESSION['user_id']}");
            
            echo json_encode(['success' => true, 'message' => 'Pedido cancelado']);
            break;
            
        case 'add_tracking':
            // Agregar tracking de envío a un pedido
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['order_id'] ?? 0;
            $carrier = $input['carrier'] ?? '';
            $trackingNumber = $input['tracking_number'] ?? '';
            
            if (!$orderId || !$carrier || !$trackingNumber) {
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit;
            }
            
            $trackingService = new ShippingTrackingService($pdo);
            
            $trackingData = [
                'order_id' => $orderId,
                'carrier' => $carrier,
                'tracking_number' => $trackingNumber,
                'shipping_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $trackingService->createTracking($trackingData);
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Admin Online Orders API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
