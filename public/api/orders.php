<?php
/**
 * API de Pedidos
 */

require_once '../../config/config.php';
require_once '../../src/controllers/OrderController.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : (is_array($_POST) ? $_POST : []);

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

$orderController = new OrderController($pdo);
$response = [];

function ensure_client_profile_id_for_user($pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $existingId = (int)$stmt->fetchColumn();
    if ($existingId > 0) {
        return $existingId;
    }

    $company = null;
    try {
        $userStmt = $pdo->prepare("SELECT COALESCE(name, '') AS full_name, COALESCE(first_name, '') AS first_name, COALESCE(last_name, '') AS last_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $full = trim((string)($user['full_name'] ?? ''));
        if ($full === '') {
            $full = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
        }
        $company = $full !== '' ? $full : 'Cliente';
    } catch (Exception $ignored) {
        $company = 'Cliente';
    }

    try {
        $insert = $pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?) ON CONFLICT (user_id) DO NOTHING");
        $insert->execute([$userId, $company]);
    } catch (Exception $ignored) {
        try {
            $insert = $pdo->prepare("INSERT INTO clients (user_id, company_name) VALUES (?, ?)");
            $insert->execute([$userId, $company]);
        } catch (Exception $ignoredTwice) {
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Método no permitido'];
                break;
            }

            // Obtener cliente_id del usuario actual
            $clientId = ensure_client_profile_id_for_user($pdo, (int)$_SESSION['user_id']);
            if ($clientId <= 0) {
                $response = ['success' => false, 'message' => 'Cliente no encontrado'];
                break;
            }

            $response = $orderController->createOrder(
                $clientId,
                $input['items'] ?? [],
                $input['is_wholesale'] ?? false,
                [
                    'weather_condition' => $input['weather_condition'] ?? null,
                    'special_event' => $input['special_event'] ?? null,
                    'notes' => $input['notes'] ?? null
                ]
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

            if (($_SESSION['role'] ?? '') === 'admin') {
                $stmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT 100");
                $stmt->execute();
                $response = ['success' => true, 'orders' => $stmt->fetchAll()];
                break;
            }

            $clientId = ensure_client_profile_id_for_user($pdo, (int)$_SESSION['user_id']);
            if ($clientId > 0) {
                $orders = $orderController->getClientOrders($clientId);
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
    if (($_SESSION['role'] ?? '') === 'admin') {
        $response['debug'] = [
            'action' => (string)$action,
            'detail' => (string)$e->getMessage()
        ];
    }
}

echo json_encode($response);
?>
