<?php
/**
 * RMA API - Return Merchandise Authorization
 * Maneja solicitudes de devolución de clientes
 */

require_once '../../config/config.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verificar sesión
require_login();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list_client':
            // Listar solicitudes RMA del cliente actual
            $stmt = $pdo->prepare("
                SELECT rma.*, st.folio as order_folio 
                FROM rma_requests rma
                LEFT JOIN sales_tickets st ON rma.order_id = st.id
                WHERE rma.user_id = ?
                ORDER BY rma.created_at DESC
            ");
            $stmt->execute([$userId]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'requests' => $requests
            ]);
            break;
            
        case 'create':
            // Crear nueva solicitud RMA
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            // Validar CSRF
            require_csrf_token();
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['order_id']) || empty($input['reason']) || empty($input['description'])) {
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }
            
            // Verificar que el pedido pertenece al usuario
            $stmt = $pdo->prepare("SELECT id FROM sales_tickets WHERE id = ? AND client_id = ?");
            $stmt->execute([$input['order_id'], $userId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no pertenece al usuario']);
                exit;
            }
            
            // Crear solicitud RMA
            $stmt = $pdo->prepare("
                INSERT INTO rma_requests (user_id, order_id, reason, description, quantity, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $result = $stmt->execute([
                $userId,
                $input['order_id'],
                $input['reason'],
                $input['description'],
                $input['quantity'] ?? 1
            ]);
            
            if ($result) {
                AppLogger::info("RMA request created by user {$userId} for order {$input['order_id']}");
                echo json_encode(['success' => true, 'message' => 'Solicitud de devolución enviada']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear solicitud']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("RMA API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
