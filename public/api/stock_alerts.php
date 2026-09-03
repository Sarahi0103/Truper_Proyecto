<?php
/**
 * API de Alertas de Stock
 * Gestión de alertas de stock bajo para administradores
 */

require_once '../../config/config.php';
require_once '../../src/Services/StockAlertService.php';
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

// Proteger operaciones de escritura de alertas
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

try {
    $stockAlertService = new StockAlertService($pdo);
    
    switch ($action) {
        case 'list':
            // Obtener alertas activas
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $stockAlertService->getActiveAlerts();
            echo json_encode($result);
            break;
            
        case 'stats':
            // Obtener estadísticas de alertas
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $stockAlertService->getAlertStats();
            echo json_encode($result);
            break;
            
        case 'scan':
            // Escanear y crear alertas automáticamente
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $stockAlertService->scanAndCreateAlerts();
            echo json_encode($result);
            break;
            
        case 'resolve':
            // Resolver alerta
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $alertId = $input['alert_id'] ?? 0;
            
            if (!$alertId) {
                echo json_encode(['success' => false, 'message' => 'ID de alerta requerido']);
                exit;
            }
            
            $result = $stockAlertService->resolveAlert($alertId, (int)$_SESSION['user_id']);
            echo json_encode($result);
            break;
            
        case 'create':
            // Crear alerta manualmente
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $stockAlertService->createManualAlert([
                'product_id' => $input['product_id'] ?? 0,
                'product_sku' => $input['product_sku'] ?? '',
                'product_name' => $input['product_name'] ?? '',
                'current_stock' => $input['current_stock'] ?? 0,
                'low_stock_threshold' => $input['low_stock_threshold'] ?? 5,
                'alert_type' => $input['alert_type'] ?? 'low_stock'
            ]);
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Stock Alerts API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
