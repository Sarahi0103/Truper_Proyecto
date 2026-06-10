<?php
/**
 * Order Controller - Truper
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';

Security::requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create') {
    Security::requirePost();
    if (!Security::verifyRequestCSRFToken()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token inválido']);
        exit();
    }

    $order_model = new Order();
    $product_model = new Product();
    $user_model = new User();

    // Sanitizar y validar items
    $items = $_POST['items'] ?? [];
    $sanitized_items = [];
    $total = 0;

    foreach ($items as $item) {
        $product_id = intval($item['product_id'] ?? 0);
        $quantity = intval($item['quantity'] ?? 0);

        if ($product_id > 0 && $quantity > 0) {
            $product = $product_model->getById($product_id);
            if ($product) {
                $sanitized_items[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $product['sell_price']
                ];
                $total += $product['sell_price'] * $quantity;
            }
        }
    }

    if (empty($sanitized_items)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No hay items válidos en el pedido']);
        exit();
    }

    // Crear orden con items (transacción DB + validación stock)
    $result = $order_model->create($_SESSION['user_id'], $total, 'pending', $sanitized_items);

    if ($result['success']) {
        $order_id = $result['order_id'];

        // Agregar puntos
        $points = floor($total / 10); // 1 punto por cada $10
        $user_model->addPoints($_SESSION['user_id'], $points);

        // Responder con JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $result['order_number'] ?? null,
            'message' => 'Orden creada exitosamente'
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Error al crear la orden']);
        exit();
    }
}

elseif ($action === 'track_payment') {
    $order_id = $_GET['order_id'] ?? null;
    
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID requerido']);
        exit();
    }
    
    require_once __DIR__ . '/../models/BarcodeReader.php';
    $payment_tracker = new PaymentTracker();
    $status = $payment_tracker->getPaymentStatus($order_id);
    
    header('Content-Type: application/json');
    echo json_encode($status);
    exit();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action no válida']);
}
?>


