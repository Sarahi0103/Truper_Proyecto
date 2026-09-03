<?php
/**
 * API para Gestión de Almacenes y Traspasos Multi-Bodega
 * Endpoint: /api/warehouses.php
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/WarehouseService.php';

header('Content-Type: application/json');

$warehouseService = new WarehouseService($pdo);
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// Proteger traspasos contra CSRF
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

try {
    switch ($action) {
        // Listar almacenes activos
        case 'list':
            $warehouses = $warehouseService->getActiveWarehouses();
            echo json_encode([
                'success' => true,
                'data' => $warehouses
            ]);
            break;

        // Consultar stock de un producto por almacén
        case 'product_stock':
            $productId = (int)($_GET['product_id'] ?? 0);
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
                exit;
            }
            $stocks = $warehouseService->getStockByProduct($productId);
            echo json_encode([
                'success' => true,
                'product_id' => $productId,
                'warehouses' => $stocks
            ]);
            break;

        // Crear traspaso de mercancía (Requiere admin/staff)
        case 'create_transfer':
            require_admin();
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $fromWarehouse = (int)($input['from_warehouse_id'] ?? 0);
            $toWarehouse = (int)($input['to_warehouse_id'] ?? 0);
            $items = $input['items'] ?? [];
            $notes = sanitize($input['notes'] ?? '');
            $userId = (int)($_SESSION['user_id'] ?? 1);

            $result = $warehouseService->createTransfer($fromWarehouse, $toWarehouse, $items, $userId, $notes);
            echo json_encode($result);
            break;

        // Completar y recibir traspaso
        case 'complete_transfer':
            require_admin();
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $transferId = (int)($input['transfer_id'] ?? 0);
            $userId = (int)($_SESSION['user_id'] ?? 1);

            $result = $warehouseService->completeTransfer($transferId, $userId);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
