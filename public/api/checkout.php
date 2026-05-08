<?php
/**
 * Checkout API - Process order and cart
 */
header('Content-Type: application/json');
require_once '../config/config.php';

// Verify session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

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

    // Calculate totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
    }

    // Calculate shipping
    $shippingCost = 0;
    if ($shippingMethod === 'express') {
        $shippingCost = 15;
    }

    $total = $subtotal + $shippingCost;

    // Resolve or create the client record linked to the current user
    $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $clientStmt->execute([$_SESSION['user_id']]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

    if ($client) {
        $clientId = (int) $client['id'];
    } else {
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

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (client_id, order_number, total_amount, payment_status, payment_amount, balance, order_date, delivery_date, notes, is_wholesale, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, false, ?)
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

    $orderId = $pdo->lastInsertId();

    // Add order items
    foreach ($cartItems as $item) {
        $productId = null;

        if (!empty($item['id'])) {
            $pstmt = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
            $pstmt->execute([(int) $item['id']]);
            $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            $productId = $product['id'] ?? null;
        }

        if (!$productId && !empty($item['sku'])) {
            $pstmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
            $pstmt->execute([$item['sku']]);
            $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            $productId = $product['id'] ?? null;
        }

        if (!$productId) {
            throw new Exception('No se pudo identificar uno de los productos del carrito');
        }

        $itemStmt = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, quantity, unit_price, subtotal, discount_percentage, discount_amount, line_total)
            VALUES (?, ?, ?, ?, ?, 0, 0, ?)
        ");

        $itemPrice = $item['price'] ?? 0;
        $itemQty = $item['quantity'] ?? 1;
        $itemSubtotal = $itemPrice * $itemQty;

        $itemResult = $itemStmt->execute([
            $orderId,
            $productId,
            $itemQty,
            $itemPrice,
            $itemSubtotal,
            $itemSubtotal
        ]);

        if (!$itemResult) {
            throw new Exception('Error al agregar item al pedido');
        }
    }

    // Create payment record
    $paymentStmt = $pdo->prepare("
        INSERT INTO payments 
        (order_id, amount, payment_method, payment_date, notes)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    $paymentMethodMap = [
        'credit_card' => 'card',
        'bank_transfer' => 'transfer',
        'on_delivery' => 'cash',
    ];

    $paymentMethodDb = $paymentMethodMap[$paymentMethod] ?? 'cash';

    $paymentStmt->execute([
        $orderId,
        $total,
        $paymentMethodDb,
        'Pago registrado desde checkout'
    ]);

    // Log action
    $logStmt = $pdo->prepare("
        INSERT INTO action_logs 
        (user_id, action, description, ip_address, timestamp)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $logStmt->execute([
        $_SESSION['user_id'],
        'order_created',
        'Pedido creado desde checkout: ' . $orderNumber,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Send confirmation email (placeholder for actual email implementation)
    // TODO: Send email with order confirmation
    // $emailResult = sendOrderConfirmationEmail($input['email'], $orderNumber, $orderId);

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
