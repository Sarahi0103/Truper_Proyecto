<?php
/**
 * API de Administración de Pedidos en Línea
 * Maneja operaciones CRUD para pedidos online (sales_tickets)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/utils/AppLogger.php';
require_once __DIR__ . '/../../src/Services/ShippingTrackingService.php';

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
            
            // Exclusivo de pedidos de Tienda en Línea:
            // 1. Folios generados por checkout web (TCK- o FOX-)
            // 2. O pedidos con paquetería externa asignada
            // 3. O pedidos con dirección de envío a domicilio válida (calle real)
            $where = "WHERE (
                st.folio LIKE 'TCK-%' 
                OR st.folio LIKE 'FOX-%' 
                OR (tr.carrier IS NOT NULL AND tr.carrier <> '')
                OR (st.shipping_address_json IS NOT NULL 
                    AND st.shipping_address_json LIKE '%\"address\":_%' 
                    AND st.shipping_address_json NOT LIKE '%\"address\":\"\"%'
                    AND st.shipping_address_json NOT LIKE '%\"address\": \"\"%'
                    AND (st.customer_name NOT LIKE '%Cotización%' OR st.order_id IS NOT NULL))
            )";
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
                SELECT st.id, st.folio, st.customer_name, st.total_amount, st.issued_date, st.order_status, 
                       st.payment_status, st.shipping_address_json, st.created_at,
                       tr.carrier, tr.tracking_number, tr.estimated_delivery
                FROM sales_tickets st
                LEFT JOIN shipping_tracking tr ON tr.order_id = st.id
                {$where}
                ORDER BY st.issued_date DESC
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
            $orderId = (int)($_GET['id'] ?? 0);
            $folio = trim((string)($_GET['folio'] ?? ''));
            
            if (!$orderId && empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'ID o Folio de pedido requerido']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT st.*, 
                       tr.carrier, tr.tracking_number, tr.estimated_delivery, tr.shipping_date,
                       COALESCE(u.email, '') as user_email,
                       COALESCE(u.phone, '') as user_phone
                FROM sales_tickets st
                LEFT JOIN shipping_tracking tr ON tr.order_id = st.id
                LEFT JOIN users u ON st.user_id = u.id
                WHERE (:id > 0 AND st.id = :id) OR (:folio <> '' AND st.folio = :folio)
                LIMIT 1
            ");
            $stmt->execute([':id' => $orderId, ':folio' => $folio]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
                exit;
            }
            
            // Cargar items de ticket_items
            $stmtItems = $pdo->prepare("
                SELECT ti.quantity, ti.unit_price, COALESCE(ti.total, ti.quantity * ti.unit_price) as line_total,
                       ti.product_name, COALESCE(p.sku, 'PROD-' || ti.product_id) as sku, p.image_url
                FROM ticket_items ti
                LEFT JOIN products p ON ti.product_id = p.id
                WHERE ti.ticket_id = ?
                ORDER BY ti.id ASC
            ");
            $stmtItems->execute([$order['id']]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items) && !empty($order['order_id'])) {
                $stmtOrderItems = $pdo->prepare("
                    SELECT oi.quantity, oi.unit_price, oi.line_total, p.name as product_name, p.sku, p.image_url
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmtOrderItems->execute([$order['order_id']]);
                $items = $stmtOrderItems->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $order['items'] = $items;
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
            
            $allowedStatuses = ['pending', 'confirmed', 'in_preparation', 'processing', 'packed', 'shipped', 'in_transit', 'delivered', 'cancelled', 'canceled'];
            
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

            try {
                $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = (SELECT order_id FROM sales_tickets WHERE id = ?)")->execute([$status, $orderId]);
            } catch (Exception $e) {}
            
            try {
                $fStmt = $pdo->prepare("SELECT folio FROM sales_tickets WHERE id = ?");
                $fStmt->execute([$orderId]);
                $oFolio = $fStmt->fetchColumn();
                if ($oFolio) {
                    $statusNames = [
                        'in_preparation' => 'En Preparación / Almacén',
                        'processing' => 'En Preparación / Almacén',
                        'packed' => 'Empacado (Etiqueta Ciega)',
                        'shipped' => 'Enviado / En Ruta de Paquetería',
                        'in_transit' => 'En Ruta de Entrega',
                        'delivered' => 'Entregado al Cliente',
                        'cancelled' => 'Cancelado',
                        'canceled' => 'Cancelado'
                    ];
                    $friendly = $statusNames[$status] ?? $status;
                    $hist = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES (?, ?, ?, ?)");
                    $hist->execute([$oFolio, $status, "Pedido actualizado a: {$friendly}", $_SESSION['name'] ?? 'Administrador']);
                }
            } catch (Exception $ignored) {}

            AppLogger::info("Order {$orderId} status updated to {$status} by user {$_SESSION['user_id']}");
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
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
                'shipping_date' => date('Y-m-d H:i:s'),
                'estimated_delivery' => $input['estimated_delivery'] ?? null
            ];
            
            $result = $trackingService->createTracking($trackingData);

            if ($result['success'] ?? false) {
                // Actualizar estado del pedido a 'shipped' y sincronizar tracking_folio
                $pdo->prepare("UPDATE sales_tickets SET order_status = 'shipped', tracking_folio = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$trackingNumber, $orderId]);

                try {
                    $fStmt = $pdo->prepare("SELECT folio FROM sales_tickets WHERE id = ?");
                    $fStmt->execute([$orderId]);
                    $oFolio = $fStmt->fetchColumn();
                    if ($oFolio) {
                        $hist = $pdo->prepare("INSERT INTO order_tracking_history (order_folio, status, notes, changed_by) VALUES (?, ?, ?, ?)");
                        $hist->execute([$oFolio, 'shipped', "Guía asignada: {$carrier} - {$trackingNumber}", $_SESSION['name'] ?? 'Administrador']);
                    }
                } catch (Exception $ignored) {}
            }
            
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
