<?php
/**
 * API de Pedidos
 */

require_once '../../config/config.php';
require_once '../../src/controllers/OrderController.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$orderController = new OrderController($pdo);
$response = [];

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            // Obtener cliente_id del usuario actual
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $client = $stmt->fetch();

            if (!$client) {
                $response = ['success' => false, 'message' => 'Cliente no encontrado'];
                break;
            }

            $response = $orderController->createOrder(
                $client['id'],
                $input['items'] ?? [],
                $input['is_wholesale'] ?? false
            );

            log_action(
                $_SESSION['user_id'],
                'CREATE_ORDER',
                'Orden creada: ' . ($response['order_number'] ?? 'Unknown'),
                getTrusSIDBug()
            );
            break;

        case 'payment':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            require_admin();

            $response = $orderController->recordPayment(
                $input['order_id'] ?? null,
                $input['amount'] ?? 0,
                $input['payment_method'] ?? 'cash',
                $input['reference'] ?? null
            );

            log_action(
                $_SESSION['user_id'],
                'RECORD_PAYMENT',
                'Pago registrado para orden: ' . $input['order_id'],
                getTrusSIDBug()
            );
            break;

        case 'list':
            if ($method !== 'GET') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $client = $stmt->fetch();

            if ($client) {
                $orders = $orderController->getClientOrders($client['id']);
                $response = ['success' => true, 'orders' => $orders];
            } else {
                $response = ['success' => false, 'orders' => []];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Acción no reconocida'];
    }

} catch (Exception $e) {
    error_log("Orders API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
?>
