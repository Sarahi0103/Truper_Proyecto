<?php
/**
 * Cart API - Manejo del carrito de compras
 * Soporta localStorage y persistencia en base de datos
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/StockReservationService.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Validar CSRF para operaciones que modifican el carrito
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

$isLogged = isset($_SESSION['user_id']);
$userId = $isLogged ? $_SESSION['user_id'] : null;
$sessionId = session_id();

$stockService = new StockReservationService($pdo);

// Verificar si la tabla shopping_carts existe
$tableExists = false;
try {
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'shopping_carts')");
    $tableExists = (bool) $stmt->fetchColumn();
} catch (Exception $e) {
    $tableExists = false;
}

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Obtener carrito del usuario o sesión
 */
if ($action === 'get' && $method === 'GET') {
    if (!$tableExists) {
        // Si no existe la tabla, devolver carrito vacío
        sendResponse(true, 'Carrito vacío (tabla no creada)', []);
    }

    try {
        $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
        $lowStockThreshold = db_column_exists('products', 'low_stock_threshold_online') ? 'low_stock_threshold_online' : 'low_stock_threshold';
        
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT sc.id, sc.product_id, sc.product_type, sc.quantity, sc.price, sc.created_at, sc.reservation_id, sr.expires_at,
                       p.name, p.sku, p.image_url, 
                       COALESCE(p.unit_price, p.sell_price, 0) as current_price,
                       COALESCE(p.price_online, p.unit_price, p.sell_price, 0) as original_price,
                       p.{$stockColumn} as available_stock,
                       COALESCE(p.{$lowStockThreshold}, 5) as low_stock_threshold,
                       COALESCE(p.discount_percentage, 0) as discount_percentage
                FROM shopping_carts sc
                LEFT JOIN products p ON sc.product_id = p.id
                LEFT JOIN stock_reservations sr ON sc.reservation_id = sr.id
                WHERE sc.user_id = ?
                ORDER BY sc.created_at DESC
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT sc.id, sc.product_id, sc.product_type, sc.quantity, sc.price, sc.created_at, sc.reservation_id, sr.expires_at,
                       p.name, p.sku, p.image_url, 
                       COALESCE(p.unit_price, p.sell_price, 0) as current_price,
                       COALESCE(p.price_online, p.unit_price, p.sell_price, 0) as original_price,
                       p.{$stockColumn} as available_stock,
                       COALESCE(p.{$lowStockThreshold}, 5) as low_stock_threshold,
                       COALESCE(p.discount_percentage, 0) as discount_percentage
                FROM shopping_carts sc
                LEFT JOIN products p ON sc.product_id = p.id
                LEFT JOIN stock_reservations sr ON sc.reservation_id = sr.id
                WHERE sc.session_id = ?
                ORDER BY sc.created_at DESC
            ");
            $stmt->execute([$sessionId]);
        }

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular stock bajo
        $lowStockItems = array_filter($items, function($item) {
            return $item['available_stock'] <= $item['low_stock_threshold'];
        });
        
        $responseData = [
            'items' => $items,
            'low_stock_count' => count($lowStockItems),
            'low_stock_items' => array_values($lowStockItems)
        ];
        
        sendResponse(true, 'Carrito obtenido', $responseData);
    } catch (Exception $e) {
        sendResponse(false, 'Error al obtener carrito: ' . $e->getMessage());
    }
}

/**
 * Agregar item al carrito
 */
if ($action === 'add' && $method === 'POST') {
    if (!$tableExists) {
        sendResponse(false, 'Tabla de carrito no existe. Ejecuta la migración CART_PERSISTENCE.sql');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $productId = $input['product_id'] ?? null;
    $productType = $input['product_type'] ?? 'catalog';
    $quantity = (int)($input['quantity'] ?? 1);
    $price = (float)($input['price'] ?? 0);

    if (!$productId || $quantity <= 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        // Verificar stock disponible
        $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            sendResponse(false, 'Producto no encontrado');
        }

        // Verificar stock (usando stock_online si existe, sino stock_quantity)
        $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
        $stockStmt = $pdo->prepare("SELECT {$stockColumn} FROM products WHERE id = ? LIMIT 1");
        $stockStmt->execute([$productId]);
        $availableStock = (int) $stockStmt->fetchColumn();

        if ($availableStock < $quantity) {
            sendResponse(false, "Stock insuficiente. Disponible: {$availableStock}");
        }

        // Verificar si ya existe en el carrito
        if ($userId) {
            $checkStmt = $pdo->prepare("
                SELECT id, quantity FROM shopping_carts 
                WHERE user_id = ? AND product_id = ? AND product_type = ?
                LIMIT 1
            ");
            $checkStmt->execute([$userId, $productId, $productType]);
        } else {
            $checkStmt = $pdo->prepare("
                SELECT id, quantity FROM shopping_carts 
                WHERE session_id = ? AND product_id = ? AND product_type = ?
                LIMIT 1
            ");
            $checkStmt->execute([$sessionId, $productId, $productType]);
        }

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            
            if ($newQuantity > $availableStock) {
                sendResponse(false, "Stock insuficiente para actualizar cantidad. Disponible: {$availableStock}");
            }

            // Actualizar reserva de stock
            $stockService->cancelReservation($existing['reservation_id'] ?? 0);
            $reservationResult = $stockService->createReservation($productId, $newQuantity, $userId, $sessionId, 15);

            $updateStmt = $pdo->prepare("
                UPDATE shopping_carts 
                SET quantity = ?, price = ?, reservation_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$newQuantity, $price, $reservationResult['reservation_id'] ?? null, $existing['id']]);
            
            sendResponse(true, 'Cantidad actualizada en carrito', ['cart_id' => $existing['id'], 'quantity' => $newQuantity]);
        } else {
            // Crear reserva de stock temporal (15 minutos)
            $reservationResult = $stockService->createReservation($productId, $quantity, $userId, $sessionId, 15);
            
            if (!($reservationResult['success'] ?? false)) {
                sendResponse(false, $reservationResult['message'] ?? 'Error al reservar stock');
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price, reservation_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $insertStmt->execute([$userId, $sessionId, $productId, $productType, $quantity, $price, $reservationResult['reservation_id'] ?? null]);
            $cartId = $insertStmt->fetchColumn();
            
            sendResponse(true, 'Producto agregado al carrito', ['cart_id' => $cartId, 'quantity' => $quantity, 'expires_at' => $reservationResult['expires_at'] ?? null]);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error al agregar al carrito: ' . $e->getMessage());
    }
}

/**
 * Actualizar cantidad de item
 */
if ($action === 'update' && $method === 'POST') {
    if (!$tableExists) {
        sendResponse(false, 'Tabla de carrito no existe');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $cartId = (int)($input['cart_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);

    if ($cartId <= 0 || $quantity < 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        // Obtener item actual para obtener reservation_id y product_id
        $stmt = $pdo->prepare("
            SELECT sc.id, sc.product_id, sc.quantity, sc.reservation_id
            FROM shopping_carts sc
            WHERE sc.id = ? AND (sc.user_id = ? OR sc.session_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$cartId, $userId, $sessionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            sendResponse(false, 'Item no encontrado en carrito');
        }

        if ($quantity === 0) {
            // Cancelar reserva y eliminar item
            if ($item['reservation_id']) {
                $stockService->cancelReservation($item['reservation_id']);
            }
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?)");
            $stmt->execute([$cartId, $userId, $sessionId]);
            sendResponse(true, 'Item eliminado del carrito');
        } else {
            // Verificar stock disponible
            $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
            $stockStmt = $pdo->prepare("
                SELECT {$stockColumn} as available_stock
                FROM products
                WHERE id = ?
                LIMIT 1
            ");
            $stockStmt->execute([$item['product_id']]);
            $availableStock = (int) $stockStmt->fetchColumn();

            if ($quantity > $availableStock) {
                sendResponse(false, "Stock insuficiente. Disponible: {$availableStock}");
            }

            // Actualizar reserva de stock
            if ($item['reservation_id']) {
                $stockService->cancelReservation($item['reservation_id']);
            }
            $reservationResult = $stockService->createReservation($item['product_id'], $quantity, $userId, $sessionId, 15);

            $updateStmt = $pdo->prepare("
                UPDATE shopping_carts 
                SET quantity = ?, reservation_id = ?, updated_at = NOW()
                WHERE id = ? AND (user_id = ? OR session_id = ?)
            ");
            $updateStmt->execute([$quantity, $reservationResult['reservation_id'] ?? null, $cartId, $userId, $sessionId]);
            
            sendResponse(true, 'Cantidad actualizada', ['quantity' => $quantity, 'expires_at' => $reservationResult['expires_at'] ?? null]);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error al actualizar: ' . $e->getMessage());
    }
}

/**
 * Eliminar item del carrito
 */
if ($action === 'remove' && $method === 'POST') {
    if (!$tableExists) {
        sendResponse(false, 'Tabla de carrito no existe');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $cartId = (int)($input['cart_id'] ?? 0);

    if ($cartId <= 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        // Obtener reservation_id antes de eliminar
        $stmt = $pdo->prepare("SELECT reservation_id FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?) LIMIT 1");
        $stmt->execute([$cartId, $userId, $sessionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cancelar reserva si existe
        if ($item && $item['reservation_id']) {
            $stockService->cancelReservation($item['reservation_id']);
        }

        $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmt->execute([$cartId, $userId, $sessionId]);
        
        sendResponse(true, 'Item eliminado del carrito');
    } catch (Exception $e) {
        sendResponse(false, 'Error al eliminar: ' . $e->getMessage());
    }
}

/**
 * Limpiar carrito completo
 */
if ($action === 'clear' && $method === 'POST') {
    if (!$tableExists) {
        sendResponse(false, 'Tabla de carrito no existe');
    }

    try {
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }
        
        sendResponse(true, 'Carrito vaciado');
    } catch (Exception $e) {
        sendResponse(false, 'Error al vaciar carrito: ' . $e->getMessage());
    }
}

/**
 * Sincronizar localStorage con base de datos
 */
if ($action === 'sync' && $method === 'POST') {
    if (!$tableExists) {
        sendResponse(false, 'Tabla de carrito no existe');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $localCart = $input['cart'] ?? [];

    if (!is_array($localCart)) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        $pdo->beginTransaction();

        // Limpiar carrito actual
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }

        // Insertar items del localStorage
        foreach ($localCart as $item) {
            $productId = (int)($item['id'] ?? 0);
            $productType = $item['product_type'] ?? 'catalog';
            $quantity = (int)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? $item['price'] ?? 0);

            if ($productId > 0 && $quantity > 0) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$userId, $sessionId, $productId, $productType, $quantity, $price]);
            }
        }

        $pdo->commit();
        sendResponse(true, 'Carrito sincronizado con base de datos');
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Error al sincronizar: ' . $e->getMessage());
    }
}

sendResponse(false, 'Acción no válida');
