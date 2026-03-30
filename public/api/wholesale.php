<?php
require_once '../../config/config.php';

require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

try {
    switch ($action) {
        case 'request':
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $stmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $client = $stmt->fetch();

            if (!$client) {
                $response = ['success' => false, 'message' => 'Perfil de cliente no encontrado'];
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO wholesalers (client_id, business_type, min_order_quantity, discount_percentage, payment_terms, is_approved)
                                   VALUES (?, ?, ?, ?, ?, false)");
            $stmt->execute([
                $client['id'],
                sanitize($input['business_type'] ?? ''),
                (int)($input['min_order_quantity'] ?? 50),
                (float)($input['discount_percentage'] ?? 15),
                sanitize($input['payment_terms'] ?? 'Contado')
            ]);

            $response = ['success' => true, 'message' => 'Solicitud de mayoreo enviada'];
            break;

        case 'approve':
            require_admin();
            if ($method !== 'POST') {
                $response = ['success' => false, 'message' => 'Metodo no permitido'];
                break;
            }

            $id = (int)($input['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE wholesalers SET is_approved = true, approved_date = NOW(), approved_by = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            $response = ['success' => true, 'message' => 'Solicitud aprobada'];
            break;

        case 'list':
            if (($_SESSION['role'] ?? '') === 'admin') {
                $stmt = $pdo->prepare("SELECT w.*, u.first_name, u.last_name
                                       FROM wholesalers w
                                       JOIN clients c ON c.id = w.client_id
                                       JOIN users u ON u.id = c.user_id
                                       ORDER BY w.requested_date DESC");
                $stmt->execute();
                $response = ['success' => true, 'items' => $stmt->fetchAll()];
                break;
            }

            $stmt = $pdo->prepare('SELECT id FROM clients WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $client = $stmt->fetch();

            if (!$client) {
                $response = ['success' => true, 'items' => []];
                break;
            }

            $stmt = $pdo->prepare('SELECT * FROM wholesalers WHERE client_id = ? ORDER BY requested_date DESC');
            $stmt->execute([$client['id']]);
            $response = ['success' => true, 'items' => $stmt->fetchAll()];
            break;

        default:
            $response = ['success' => false, 'message' => 'Accion no valida'];
    }
} catch (Exception $e) {
    error_log('Wholesale API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Error del servidor'];
}

echo json_encode($response);
