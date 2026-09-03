<?php
/**
 * API de Shipping Tracking
 * Maneja operaciones de tracking de envíos con paqueterías
 */

require_once '../../config/config.php';
require_once '../../src/Services/ShippingTrackingService.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Operaciones de escritura requieren login, rol admin/empleado y CSRF
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_login();
    require_csrf_token();
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, ['admin', 'employee'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
}

try {
    $trackingService = new ShippingTrackingService($pdo);
    
    switch ($action) {
        case 'get':
            // Obtener tracking de un pedido por folio o order_id
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $folio = $_GET['folio'] ?? '';
            $orderId = $_GET['order_id'] ?? 0;
            
            if (empty($folio) && !$orderId) {
                echo json_encode(['success' => false, 'message' => 'Folio o order_id requerido']);
                exit;
            }
            
            // Obtener ID del pedido por folio si no se proporcionó order_id
            if (empty($folio) === false && !$orderId) {
                $stmt = $pdo->prepare("SELECT id FROM sales_tickets WHERE folio = ? LIMIT 1");
                $stmt->execute([$folio]);
                $orderId = $stmt->fetchColumn();
                
                if (!$orderId) {
                    echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
                    exit;
                }
            }
            
            $tracking = $trackingService->getOrderTracking($orderId);
            
            if ($tracking) {
                // Decodificar eventos JSON si existe
                if (isset($tracking['tracking_events']) && is_string($tracking['tracking_events'])) {
                    $tracking['tracking_events'] = json_decode($tracking['tracking_events'], true) ?: [];
                }
                echo json_encode(['success' => true, 'tracking' => $tracking]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay tracking disponible']);
            }
            break;
            
        case 'sync':
            // Sincronizar tracking desde API de paquetería
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $trackingId = $input['tracking_id'] ?? 0;
            
            if (!$trackingId) {
                echo json_encode(['success' => false, 'message' => 'ID de tracking requerido']);
                exit;
            }
            
            $result = $trackingService->syncTracking($trackingId);
            
            echo json_encode($result);
            break;
            
        case 'create':
            // Crear tracking para un pedido (admin)
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            require_login();
            $userRole = $_SESSION['role'] ?? '';
            if (!in_array($userRole, ['admin', 'employee'])) {
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $trackingData = [
                'order_id' => $input['order_id'] ?? 0,
                'carrier' => $input['carrier'] ?? '',
                'tracking_number' => $input['tracking_number'] ?? '',
                'shipping_date' => $input['shipping_date'] ?? date('Y-m-d H:i:s'),
                'estimated_delivery' => $input['estimated_delivery'] ?? null,
                'shipping_address' => $input['shipping_address'] ?? []
            ];
            
            if (!$trackingData['order_id'] || !$trackingData['carrier'] || !$trackingData['tracking_number']) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit;
            }
            
            $result = $trackingService->createTracking($trackingData);
            
            // Actualizar estado del pedido a 'shipped'
            if ($result['success']) {
                $pdo->prepare("UPDATE sales_tickets SET order_status = 'shipped', updated_at = NOW() WHERE id = ?")
                    ->execute([$trackingData['order_id']]);
                
                // Registrar log
                log_action($_SESSION['user_id'], 'CREATE_TRACKING', "Tracking creado para orden {$trackingData['order_id']}: {$trackingData['tracking_number']}");
            }
            
            echo json_encode($result);
            break;
            
        case 'carriers':
            // Obtener lista de paqueterías disponibles
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $carriers = $trackingService->getCarriers();
            echo json_encode(['success' => true, 'carriers' => $carriers]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Shipping Tracking API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
