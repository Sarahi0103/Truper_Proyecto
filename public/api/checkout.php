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

    // Calculate totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
    }

    // Calculate shipping
    $shippingCost = 0;
    if ($input['shippingMethod'] === 'express') {
        $shippingCost = 15;
    }

    $total = $subtotal + $shippingCost;

    // Generate order number
    $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (client_id, order_number, total_amount, status, payment_status, order_date, delivery_date, notes, payment_terms, payment_due_date, is_wholesale, balance)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, false, ?)
    ");

    $deliveryDate = date('Y-m-d', strtotime('+5 days'));
    if ($input['shippingMethod'] === 'express') {
        $deliveryDate = date('Y-m-d', strtotime('+2 days'));
    }

    $notes = "Dirección: " . $input['address'] . ", " . $input['city'] . "\n";
    if (!empty($input['deliveryNotes'])) {
        $notes .= "Notas de entrega: " . $input['deliveryNotes'] . "\n";
    }
    if (!empty($input['orderNotes'])) {
        $notes .= "Notas: " . $input['orderNotes'];
    }

    $paymentTerms = $input['paymentMethod'] === 'on_delivery' ? '30_days' : 'immediate';
    $paymentDueDate = $paymentTerms === '30_days' ? date('Y-m-d', strtotime('+30 days')) : date('Y-m-d');

    $result = $stmt->execute([
        $_SESSION['user_id'],  // client_id (using user_id as client placeholder)
        $orderNumber,
        $total,
        'pending',
        'pending',
        $deliveryDate,
        $notes,
        $paymentTerms,
        $paymentDueDate,
        $total
    ]);

    if (!$result) {
        throw new Exception('Error al crear la orden');
    }

    $orderId = $pdo->lastInsertId();

    // Add order items
    foreach ($cartItems as $item) {
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        $itemPrice = $item['price'] ?? 0;
        $itemQty = $item['quantity'] ?? 1;
        $itemSubtotal = $itemPrice * $itemQty;

        // Try to find product by ID or SKU
        $productId = null;
        if (!empty($item['id'])) {
            $pstmt = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
            $pstmt->execute([$item['id']]);
            $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            $productId = $product['id'] ?? null;
        }

        if (!$productId && !empty($item['sku'])) {
            $pstmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
            $pstmt->execute([$item['sku']]);
            $product = $pstmt->fetch(PDO::FETCH_ASSOC);
            $productId = $product['id'] ?? null;
        }

        $itemResult = $itemStmt->execute([
            $orderId,
            $productId,
            $itemQty,
            $itemPrice,
            $itemSubtotal
        ]);

        if (!$itemResult) {
            throw new Exception('Error al agregar item al pedido');
        }
    }

    // Create payment record
    $paymentStmt = $pdo->prepare("
        INSERT INTO payments 
        (order_id, amount, status, payment_date, payment_method)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    $paymentMethod = $input['paymentMethod'] ?? 'credit_card';
    $paymentStatus = $paymentMethod === 'on_delivery' ? 'pending' : 'pending';

    $paymentStmt->execute([
        $orderId,
        $total,
        $paymentStatus,
        $paymentMethod
    ]);

    // Log action
    $logStmt = $pdo->prepare("
        INSERT INTO action_logs 
        (user_id, action, entity_type, entity_id, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $logStmt->execute([
        $_SESSION['user_id'],
        'order_created',
        'order',
        $orderId,
        'Pedido creado desde checkout: ' . $orderNumber
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
