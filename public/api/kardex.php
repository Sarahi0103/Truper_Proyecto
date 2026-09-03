<?php
/**
 * API para Consulta y Registro en Kardex de Inventario
 * Endpoint: /api/kardex.php
 * Truper Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/KardexService.php';

header('Content-Type: application/json');

// Kardex es accesible por administradores y encargados de inventario
require_admin();

$kardexService = new KardexService($pdo);
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        // Listar movimientos con filtros
        case 'list':
            $productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
            $startDate = sanitize($_GET['start_date'] ?? '');
            $endDate = sanitize($_GET['end_date'] ?? '');
            $movementType = sanitize($_GET['movement_type'] ?? 'all');
            $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $movements = $kardexService->getMovements($productId, $startDate, $endDate, $movementType, $limit, $offset);

            echo json_encode([
                'success' => true,
                'data' => $movements,
                'count' => count($movements)
            ]);
            break;

        // Registrar ajuste manual o entrada/salida extraordinaria
        case 'record_adjustment':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $productId = (int)($input['product_id'] ?? 0);
            $movementType = sanitize($input['movement_type'] ?? 'adjustment');
            $quantity = (int)($input['quantity'] ?? 0);
            $unitCost = (float)($input['unit_cost'] ?? 0);
            $unitPrice = (float)($input['unit_price'] ?? 0);
            $referenceFolio = sanitize($input['reference_folio'] ?? 'AJUSTE-MANUAL');
            $warehouseId = !empty($input['warehouse_id']) ? (int)$input['warehouse_id'] : null;
            $notes = sanitize($input['notes'] ?? 'Ajuste manual desde módulo de inventario');
            $userId = (int)($_SESSION['user_id'] ?? 1);

            if ($productId <= 0 || $quantity == 0) {
                echo json_encode(['success' => false, 'message' => 'Producto y cantidad inválidos']);
                exit;
            }

            $result = $kardexService->recordMovement(
                $productId, $movementType, $quantity, $unitCost, $unitPrice,
                $referenceFolio, $warehouseId, $userId, $notes
            );

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
        'message' => 'Error al procesar consulta de kardex: ' . $e->getMessage()
    ]);
}
