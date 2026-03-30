<?php
/**
 * Order Controller - TRUPPER
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';

Security::requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create') {
    $order_model = new Order();
    $product_model = new Product();
    $user_model = new User();
    
    $items = $_POST['items'] ?? []; // Array de [product_id => quantity]
    $total = 0;
    
    // Calcular total
    foreach ($items as $product_id => $quantity) {
        $product = $product_model->getById($product_id);
        if ($product) {
            $total += $product['sell_price'] * $quantity;
        }
    }
    
    // Crear orden
    $result = $order_model->create($_SESSION['user_id'], $total);
    
    if ($result['success']) {
        $order_id = $result['order_id'];
        
        // Agregar items
        foreach ($items as $product_id => $quantity) {
            $product = $product_model->getById($product_id);
            if ($product) {
                $order_model->addItem($order_id, $product_id, $quantity, $product['sell_price']);
            }
        }
        
        // Agregar puntos
        $points = floor($total / 10); // 1 punto por cada $10
        $user_model->addPoints($_SESSION['user_id'], $points);
        
        // Responder con JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Orden creada exitosamente']);
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
