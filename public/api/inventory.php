<?php
/**
 * API de Inventario
 * Maneja operaciones de búsqueda y actualización de stock para el escáner
 */

require_once '../../config/config.php';
require_once '../../src/Services/InventoryService.php';
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

// Proteger operaciones que modifican inventario
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

try {
    $inventoryService = new InventoryService($pdo);
    
    switch ($action) {
        case 'search':
            // Buscar producto por código de barras o SKU
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $code = $_GET['code'] ?? '';
            if (empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Código requerido']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, sku, stock_quantity, stock_online, stock_local, price, price_online
                FROM products
                WHERE sku = ? OR barcode = ?
                LIMIT 1
            ");
            $stmt->execute([$code, $code]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            }
            break;
            
        case 'update':
            // Actualizar stock de producto
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? 0;
            $column = $input['column'] ?? 'stock_online';
            $quantity = $input['quantity'] ?? 0;
            
            if (!$productId || !$quantity) {
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                exit;
            }
            
            // Validar columna
            if (!in_array($column, ['stock_online', 'stock_local'])) {
                echo json_encode(['success' => false, 'message' => 'Columna inválida']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE products
                SET {$column} = {$column} + ?,
                    updated_at = NOW()
                WHERE id = ?
                RETURNING {$column}
            ");
            $stmt->execute([$quantity, $productId]);
            $newStock = $stmt->fetchColumn();
            
            AppLogger::info("Stock updated: Product {$productId}, {$column} +{$quantity}, new value: {$newStock}");
            
            echo json_encode(['success' => true, 'new_stock' => $newStock]);
            break;
            
        case 'low_stock':
            // Obtener productos con bajo stock online
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $lowStockProducts = $inventoryService->getLowStockOnline();
            echo json_encode(['success' => true, 'products' => $lowStockProducts]);
            break;
            
        case 'transfer':
            // Transferir stock entre online y local
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['product_id'] ?? 0;
            $direction = $input['direction'] ?? 'to_online'; // 'to_online' o 'to_local'
            $quantity = $input['quantity'] ?? 0;
            
            if (!$productId || !$quantity) {
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                exit;
            }
            
            if ($direction === 'to_online') {
                $result = $inventoryService->transferStockToOnline($productId, $quantity);
            } else {
                $result = $inventoryService->transferStockToLocal($productId, $quantity);
            }
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Inventory API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
