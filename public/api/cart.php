<?php
/**
 * Cart API - Manejo del carrito de compras
 * Soporta localStorage y persistencia en base de datos con auto-recuperación de esquema
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

// Auto-verificar y auto-migrar tabla shopping_carts
$tableExists = false;
try {
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'shopping_carts')");
    $tableExists = (bool) $stmt->fetchColumn();
    
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS shopping_carts (
                id SERIAL PRIMARY KEY,
                user_id INTEGER,
                session_id VARCHAR(255),
                product_id INTEGER NOT NULL,
                product_type VARCHAR(20) DEFAULT 'catalog',
                quantity INTEGER NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                reservation_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $tableExists = true;
    } else {
        // Auto-heal missing columns if table already existed from older schema
        if (!db_column_exists('shopping_carts', 'reservation_id')) {
            @$pdo->exec("ALTER TABLE shopping_carts ADD COLUMN IF NOT EXISTS reservation_id INTEGER");
        }
        if (!db_column_exists('shopping_carts', 'product_type')) {
            @$pdo->exec("ALTER TABLE shopping_carts ADD COLUMN IF NOT EXISTS product_type VARCHAR(20) DEFAULT 'catalog'");
        }
        if (!db_column_exists('shopping_carts', 'price')) {
            @$pdo->exec("ALTER TABLE shopping_carts ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0");
        }
    }
} catch (Exception $e) {
    // Si falla el DDL, continuar de forma defensiva
}

// Re-verificar existencia de columnas
$hasReservationCol = db_column_exists('shopping_carts', 'reservation_id');
$hasProductTypeCol = db_column_exists('shopping_carts', 'product_type');
$hasPriceCol = db_column_exists('shopping_carts', 'price');

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * 1. Obtener carrito del usuario o sesión
 */
if ($action === 'get' && $method === 'GET') {
    if (!$tableExists) {
        sendResponse(true, 'Carrito vacío', []);
    }

    try {
        $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
        $lowStockThreshold = db_column_exists('products', 'low_stock_threshold_online') ? 'low_stock_threshold_online' : 'low_stock_threshold';
        
        $requestedType = $_GET['product_type'] ?? $_GET['type'] ?? null;
        $typeCondition = '';
        if ($requestedType && $hasProductTypeCol) {
            if ($requestedType === 'online') {
                $typeCondition = " AND sc.product_type = 'online' ";
            } else {
                $typeCondition = " AND (sc.product_type = 'catalog' OR sc.product_type IS NULL OR sc.product_type = '') ";
            }
        }

        $resJoin = $hasReservationCol ? "LEFT JOIN stock_reservations sr ON sc.reservation_id = sr.id" : "";
        $resSelect = $hasReservationCol ? "sc.reservation_id, sr.expires_at," : "NULL as reservation_id, NULL as expires_at,";
        $typeSelect = $hasProductTypeCol ? "sc.product_type," : "'catalog' as product_type,";
        $priceSelect = $hasPriceCol ? "sc.price," : "0 as price,";
        
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT sc.id, sc.product_id, {$typeSelect} sc.quantity, {$priceSelect} sc.created_at, {$resSelect}
                       p.name, p.sku, p.image_url, 
                       COALESCE(p.unit_price, p.sell_price, 0) as current_price,
                       COALESCE(p.price_online, p.unit_price, p.sell_price, 0) as original_price,
                       p.{$stockColumn} as available_stock,
                       COALESCE(p.{$lowStockThreshold}, 5) as low_stock_threshold,
                       COALESCE(p.discount_percentage, 0) as discount_percentage
                FROM shopping_carts sc
                LEFT JOIN products p ON sc.product_id = p.id
                {$resJoin}
                WHERE sc.user_id = ? {$typeCondition}
                ORDER BY sc.created_at DESC
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT sc.id, sc.product_id, {$typeSelect} sc.quantity, {$priceSelect} sc.created_at, {$resSelect}
                       p.name, p.sku, p.image_url, 
                       COALESCE(p.unit_price, p.sell_price, 0) as current_price,
                       COALESCE(p.price_online, p.unit_price, p.sell_price, 0) as original_price,
                       p.{$stockColumn} as available_stock,
                       COALESCE(p.{$lowStockThreshold}, 5) as low_stock_threshold,
                       COALESCE(p.discount_percentage, 0) as discount_percentage
                FROM shopping_carts sc
                LEFT JOIN products p ON sc.product_id = p.id
                {$resJoin}
                WHERE sc.session_id = ? {$typeCondition}
                ORDER BY sc.created_at DESC
            ");
            $stmt->execute([$sessionId]);
        }

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $lowStockItems = array_filter($items, function($item) {
            return ($item['available_stock'] ?? 0) <= ($item['low_stock_threshold'] ?? 5);
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
 * 2. Agregar item al carrito
 */
if ($action === 'add' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $productId = $input['product_id'] ?? null;
    $productType = $input['product_type'] ?? 'catalog';
    $quantity = (int)($input['quantity'] ?? 1);
    $price = (float)($input['price'] ?? 0);

    if (!$productId || $quantity <= 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        // Verificar producto y stock
        $stmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            sendResponse(false, 'Producto no encontrado');
        }

        $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
        $stockStmt = $pdo->prepare("SELECT {$stockColumn} FROM products WHERE id = ? LIMIT 1");
        $stockStmt->execute([$productId]);
        $availableStock = (int) $stockStmt->fetchColumn();

        if ($availableStock < $quantity) {
            sendResponse(false, "Stock insuficiente. Disponible: {$availableStock}");
        }

        // Verificar si ya existe en el carrito
        $typeCondition = $hasProductTypeCol ? "AND product_type = ?" : "";
        $typeParams = $hasProductTypeCol ? [$productType] : [];

        if ($userId) {
            $checkStmt = $pdo->prepare("
                SELECT id, quantity " . ($hasReservationCol ? ", reservation_id" : "") . " 
                FROM shopping_carts 
                WHERE user_id = ? AND product_id = ? {$typeCondition}
                LIMIT 1
            ");
            $checkStmt->execute(array_merge([$userId, $productId], $typeParams));
        } else {
            $checkStmt = $pdo->prepare("
                SELECT id, quantity " . ($hasReservationCol ? ", reservation_id" : "") . " 
                FROM shopping_carts 
                WHERE session_id = ? AND product_id = ? {$typeCondition}
                LIMIT 1
            ");
            $checkStmt->execute(array_merge([$sessionId, $productId], $typeParams));
        }

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Intentar reserva opcional
        $reservationResult = null;
        try {
            if ($existing && !empty($existing['reservation_id'])) {
                $stockService->cancelReservation((int)$existing['reservation_id']);
            }
            $reservationResult = $stockService->createReservation($productId, ($existing ? $existing['quantity'] + $quantity : $quantity), $userId, $sessionId, 15);
        } catch (Exception $e) {
            // Reserva opcional, no bloquear carrito
        }

        if ($existing) {
            $newQuantity = $existing['quantity'] + $quantity;
            if ($newQuantity > $availableStock) {
                sendResponse(false, "Stock insuficiente para actualizar cantidad. Disponible: {$availableStock}");
            }

            if ($hasReservationCol) {
                $updateStmt = $pdo->prepare("
                    UPDATE shopping_carts 
                    SET quantity = ?, price = ?, reservation_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newQuantity, $price, $reservationResult['reservation_id'] ?? null, $existing['id']]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE shopping_carts 
                    SET quantity = ?, price = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$newQuantity, $price, $existing['id']]);
            }
            
            sendResponse(true, 'Cantidad actualizada en carrito', ['cart_id' => $existing['id'], 'quantity' => $newQuantity]);
        } else {
            if ($hasReservationCol) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price, reservation_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    RETURNING id
                ");
                $insertStmt->execute([$userId, $sessionId, $productId, $productType, $quantity, $price, $reservationResult['reservation_id'] ?? null]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price)
                    VALUES (?, ?, ?, ?, ?, ?)
                    RETURNING id
                ");
                $insertStmt->execute([$userId, $sessionId, $productId, $productType, $quantity, $price]);
            }
            $cartId = $insertStmt->fetchColumn();
            
            sendResponse(true, 'Producto agregado al carrito', [
                'cart_id' => $cartId,
                'quantity' => $quantity,
                'expires_at' => $reservationResult['expires_at'] ?? null
            ]);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error al agregar al carrito: ' . $e->getMessage());
    }
}

/**
 * 3. Actualizar cantidad de item
 */
if ($action === 'update' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cartId = (int)($input['cart_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);

    if ($cartId <= 0 || $quantity < 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT sc.id, sc.product_id, sc.quantity " . ($hasReservationCol ? ", sc.reservation_id" : "") . "
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
            if (!empty($item['reservation_id'])) {
                @$stockService->cancelReservation((int)$item['reservation_id']);
            }
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?)");
            $stmt->execute([$cartId, $userId, $sessionId]);
            sendResponse(true, 'Item eliminado del carrito');
        } else {
            $stockColumn = db_column_exists('products', 'stock_online') ? 'stock_online' : 'stock_quantity';
            $stockStmt = $pdo->prepare("SELECT {$stockColumn} as available_stock FROM products WHERE id = ? LIMIT 1");
            $stockStmt->execute([$item['product_id']]);
            $availableStock = (int) $stockStmt->fetchColumn();

            if ($quantity > $availableStock) {
                sendResponse(false, "Stock insuficiente. Disponible: {$availableStock}");
            }

            $reservationResult = null;
            try {
                if (!empty($item['reservation_id'])) {
                    @$stockService->cancelReservation((int)$item['reservation_id']);
                }
                $reservationResult = $stockService->createReservation($item['product_id'], $quantity, $userId, $sessionId, 15);
            } catch (Exception $e) {}

            if ($hasReservationCol) {
                $updateStmt = $pdo->prepare("
                    UPDATE shopping_carts 
                    SET quantity = ?, reservation_id = ?, updated_at = NOW()
                    WHERE id = ? AND (user_id = ? OR session_id = ?)
                ");
                $updateStmt->execute([$quantity, $reservationResult['reservation_id'] ?? null, $cartId, $userId, $sessionId]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE shopping_carts 
                    SET quantity = ?, updated_at = NOW()
                    WHERE id = ? AND (user_id = ? OR session_id = ?)
                ");
                $updateStmt->execute([$quantity, $cartId, $userId, $sessionId]);
            }
            
            sendResponse(true, 'Cantidad actualizada', ['quantity' => $quantity]);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error al actualizar: ' . $e->getMessage());
    }
}

/**
 * 4. Eliminar item del carrito
 */
if ($action === 'remove' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cartId = (int)($input['cart_id'] ?? 0);

    if ($cartId <= 0) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        if ($hasReservationCol) {
            $stmt = $pdo->prepare("SELECT reservation_id FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?) LIMIT 1");
            $stmt->execute([$cartId, $userId, $sessionId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item && !empty($item['reservation_id'])) {
                @$stockService->cancelReservation((int)$item['reservation_id']);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmt->execute([$cartId, $userId, $sessionId]);
        
        sendResponse(true, 'Item eliminado del carrito');
    } catch (Exception $e) {
        sendResponse(false, 'Error al eliminar: ' . $e->getMessage());
    }
}

/**
 * 5. Limpiar carrito completo
 */
if ($action === 'clear' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $requestedType = $input['product_type'] ?? $_GET['product_type'] ?? null;

    try {
        $typeCondition = '';
        if ($requestedType && $hasProductTypeCol) {
            if ($requestedType === 'online') {
                $typeCondition = " AND product_type = 'online' ";
            } else {
                $typeCondition = " AND (product_type = 'catalog' OR product_type IS NULL OR product_type = '') ";
            }
        }

        if ($userId) {
            if ($hasReservationCol) {
                $stmt = $pdo->prepare("SELECT reservation_id FROM shopping_carts WHERE user_id = ? {$typeCondition} AND reservation_id IS NOT NULL");
                $stmt->execute([$userId]);
                $resIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($resIds as $rId) {
                    if ($rId) @$stockService->cancelReservation((int)$rId);
                }
            }
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE user_id = ? {$typeCondition}");
            $stmt->execute([$userId]);
        } else {
            if ($hasReservationCol) {
                $stmt = $pdo->prepare("SELECT reservation_id FROM shopping_carts WHERE session_id = ? {$typeCondition} AND reservation_id IS NOT NULL");
                $stmt->execute([$sessionId]);
                $resIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($resIds as $rId) {
                    if ($rId) @$stockService->cancelReservation((int)$rId);
                }
            }
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE session_id = ? {$typeCondition}");
            $stmt->execute([$sessionId]);
        }
        
        sendResponse(true, 'Carrito vaciado');
    } catch (Exception $e) {
        sendResponse(false, 'Error al vaciar carrito: ' . $e->getMessage());
    }
}

/**
 * 6. Sincronizar localStorage con base de datos
 */
if ($action === 'sync' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $localCart = $input['cart'] ?? [];
    $requestedType = $input['product_type'] ?? $_GET['product_type'] ?? null;

    if (!is_array($localCart)) {
        sendResponse(false, 'Datos inválidos');
    }

    try {
        $pdo->beginTransaction();

        $typeCondition = '';
        if ($requestedType && $hasProductTypeCol) {
            if ($requestedType === 'online') {
                $typeCondition = " AND product_type = 'online' ";
            } else {
                $typeCondition = " AND (product_type = 'catalog' OR product_type IS NULL OR product_type = '') ";
            }
        }

        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE user_id = ? {$typeCondition}");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM shopping_carts WHERE session_id = ? {$typeCondition}");
            $stmt->execute([$sessionId]);
        }

        foreach ($localCart as $item) {
            $productId = (int)($item['id'] ?? 0);
            $productType = $item['product_type'] ?? 'catalog';
            $quantity = (int)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? $item['price'] ?? 0);

            if ($productId > 0 && $quantity > 0) {
                if ($hasProductTypeCol && $hasPriceCol) {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO shopping_carts (user_id, session_id, product_id, product_type, quantity, price)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([$userId, $sessionId, $productId, $productType, $quantity, $price]);
                } else {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO shopping_carts (user_id, session_id, product_id, quantity)
                        VALUES (?, ?, ?, ?)
                    ");
                    $insertStmt->execute([$userId, $sessionId, $productId, $quantity]);
                }
            }
        }

        $pdo->commit();
        sendResponse(true, 'Carrito sincronizado con base de datos');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Error al sincronizar: ' . $e->getMessage());
    }
}

sendResponse(false, 'Acción no válida');
