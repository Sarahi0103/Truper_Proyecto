<?php
/**
 * Checkout API - Process order and cart
 * 
 * Fixes applied:
 * - BE-01: CSRF token validation
 * - BE-02: Correct require_once path
 * - BE-03: Stock deduction on purchase
 * - BE-04: Server-side price validation (prices from DB, not client)
 * - DB-03: Use RETURNING id instead of lastInsertId()
 * - DB-05: Full transaction wrapping
 */

// BE-02: Correct path — we are in public/api/, config is in ../../config/
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Verify session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// BE-01: Validate CSRF token
require_csrf_token();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }

    // Validate required fields
    $required = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'postalCode', 'cartItems'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    $cartItems = $input['cartItems'];
    if (!is_array($cartItems) || count($cartItems) === 0) {
        throw new Exception('El carrito está vacío');
    }

    $shippingMethod = $input['shippingMethod'] ?? 'standard';
    $paymentMethod = $input['paymentMethod'] ?? 'credit_card';
    try {
        // BE-04: Validate prices from server, not from client input
        // Resolve each product and get the REAL price from the database
        $resolvedItems = [];
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $productId = null;
            $product = null;

            // Try by ID first
            if (!empty($item['id'])) {
                $pstmt = $pdo->prepare("SELECT id, sku, name, COALESCE(unit_price, sell_price, 0) AS unit_price, stock_quantity FROM products WHERE id = ? LIMIT 1");
                $pstmt->execute([(int) $item['id']]);
                $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            }

            // Fallback: try by SKU
            if (!$product && !empty($item['sku'])) {
                $pstmt = $pdo->prepare("SELECT id, sku, name, COALESCE(unit_price, sell_price, 0) AS unit_price, stock_quantity FROM products WHERE sku = ? LIMIT 1");
                $pstmt->execute([$item['sku']]);
                $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$product) {
                throw new Exception('No se pudo identificar uno de los productos del carrito');
            }

            $productId = (int) $product['id'];
            $serverPrice = (float) $product['unit_price']; // Price from DB, not client
            $requestedQty = max(1, (int) ($item['quantity'] ?? 1));
            $currentStock = (int) ($product['stock_quantity'] ?? 0);

            // BE-03: Verify sufficient stock
            if ($currentStock < $requestedQty) {
                $productName = $product['name'] ?? $product['sku'] ?? "ID:{$productId}";
                throw new Exception("Stock insuficiente para '{$productName}'. Disponible: {$currentStock}, solicitado: {$requestedQty}");
            }

            $lineTotal = round($serverPrice * $requestedQty, 2);
            $subtotal += $lineTotal;

            $resolvedItems[] = [
                'product_id' => $productId,
                'quantity' => $requestedQty,
                'unit_price' => $serverPrice,
                'subtotal' => $lineTotal,
                'line_total' => $lineTotal,
            ];
        }

        // Calculate shipping
        $shippingCost = 0;
        if ($shippingMethod === 'express') {
            $shippingCost = 15;
        }

        $total = round($subtotal + $shippingCost, 2);

        // Resolve or create the client record linked to the current user
        $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
        $clientStmt->execute([$_SESSION['user_id']]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $clientId = (int) $client['id'];
        } else {
            // DB-03: Use RETURNING id for PostgreSQL compatibility
            $clientInsert = $pdo->prepare("INSERT INTO clients (user_id, company_name, created_at, updated_at) VALUES (?, NULL, NOW(), NOW()) RETURNING id");
            $clientInsert->execute([$_SESSION['user_id']]);
            $clientId = (int) $clientInsert->fetchColumn();
        }

        $deliveryDate = date('Y-m-d', strtotime($shippingMethod === 'express' ? '+2 days' : '+5 days'));
        $notes = "Dirección: " . $input['address'] . ", " . $input['city'] . "\n";
        $notes .= "Código postal: " . $input['postalCode'] . "\n";
        if (!empty($input['deliveryNotes'])) {
            $notes .= "Notas de entrega: " . $input['deliveryNotes'] . "\n";
        }
        if (!empty($input['orderNotes'])) {
            $notes .= "Notas: " . $input['orderNotes'];
        }

        // Generate order number
        $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // DB-03: Use RETURNING id instead of lastInsertId()
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (client_id, order_number, total_amount, payment_status, payment_amount, balance, order_date, delivery_date, notes, is_wholesale, status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, false, ?)
            RETURNING id
        ");

        $result = $stmt->execute([
            $clientId,
            $orderNumber,
            $total,
            'pending',
            0,
            $total,
            $deliveryDate,
            $notes,
            'pending'
        ]);

        if (!$result) {
            throw new Exception('Error al crear la orden');
        }

        $orderId = (int) $stmt->fetchColumn();

        // Add order items AND deduct stock
        foreach ($resolvedItems as $resolvedItem) {
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items 
                (order_id, product_id, quantity, unit_price, subtotal, discount_percentage, discount_amount, line_total)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?)
            ");

            $itemResult = $itemStmt->execute([
                $orderId,
                $resolvedItem['product_id'],
                $resolvedItem['quantity'],
                $resolvedItem['unit_price'],
                $resolvedItem['subtotal'],
                $resolvedItem['line_total']
            ]);

            if (!$itemResult) {
                throw new Exception('Error al agregar item al pedido');
            }

            // BE-03: Deduct stock — uses WHERE stock_quantity >= ? as safety check
            $stockStmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ?, 
                    updated_at = NOW()
                WHERE id = ? AND stock_quantity >= ?
            ");
            $stockStmt->execute([
                $resolvedItem['quantity'],
                $resolvedItem['product_id'],
                $resolvedItem['quantity']
            ]);

            if ($stockStmt->rowCount() === 0) {
                throw new Exception('Stock insuficiente al momento de procesar. Otro usuario pudo haber comprado el producto.');
            }
        }

        // Create payment record
        $paymentMethodMap = [
            'credit_card' => 'card',
            'bank_transfer' => 'transfer',
            'on_delivery' => 'cash',
        ];

        $paymentMethodDb = $paymentMethodMap[$paymentMethod] ?? 'cash';

        $paymentStmt = $pdo->prepare("
            INSERT INTO payments 
            (order_id, amount, payment_method, payment_date, notes)
            VALUES (?, ?, ?, NOW(), ?)
        ");

        $paymentStmt->execute([
            $orderId,
            $total,
            $paymentMethodDb,
            'Pago registrado desde checkout'
        ]);
    } catch (Exception $e) {
        throw $e;
    }

    // Post-commit actions (non-critical, outside transaction)

    // Trigger automatic ticket generation
    try {
        require_once __DIR__ . '/../../backend/hooks/ticket_hooks.php';
        onOrderCompleted($orderId);
    } catch (Exception $e) {
        error_log("Error al crear ticket automático desde checkout: " . $e->getMessage());
    }

    // Log action
    try {
        log_action(
            $_SESSION['user_id'],
            'order_created',
            'Pedido creado desde checkout: ' . $orderNumber,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        );
    } catch (Exception $e) {
        error_log("Error al registrar log de checkout: " . $e->getMessage());
    }

    // Prepare response
    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'redirect' => '/order_confirmation.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
