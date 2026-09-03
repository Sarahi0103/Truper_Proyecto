<?php
/**
 * API de Reembolsos
 * Gestión de reembolsos automáticos y notas de crédito
 */

require_once '../../config/config.php';
require_once '../../src/Services/RefundService.php';
require_once '../../src/utils/AppLogger.php';

header('Content-Type: application/json');

// Verificar sesión y rol
require_login();
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'employee'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Proteger operaciones de reembolsos y notas de crédito
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
}

try {
    $refundService = new RefundService($pdo);
    
    switch ($action) {
        case 'create':
            // Crear solicitud de reembolso
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $refundService->createRefund([
                'order_id' => $input['order_id'] ?? 0,
                'payment_id' => $input['payment_id'] ?? null,
                'refund_amount' => $input['refund_amount'] ?? 0,
                'refund_reason' => $input['refund_reason'] ?? '',
                'refund_method' => $input['refund_method'] ?? 'cash'
            ]);
            
            echo json_encode($result);
            break;
            
        case 'process':
            // Procesar reembolso
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $refundId = $input['refund_id'] ?? 0;
            
            if (!$refundId) {
                echo json_encode(['success' => false, 'message' => 'ID de reembolso requerido']);
                exit;
            }
            
            $result = $refundService->processRefund($refundId, (int)$_SESSION['user_id']);
            echo json_encode($result);
            break;
            
        case 'pending':
            // Obtener reembolsos pendientes
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $result = $refundService->getPendingRefunds();
            echo json_encode($result);
            break;
            
        case 'credit_note':
            // Crear nota de crédito
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $refundService->createCreditNote([
                'order_id' => $input['order_id'] ?? 0,
                'client_id' => $input['client_id'] ?? 0,
                'amount' => $input['amount'] ?? 0,
                'reason' => $input['reason'] ?? '',
                'expires_days' => $input['expires_days'] ?? 365
            ]);
            
            echo json_encode($result);
            break;
            
        case 'use_credit_note':
            // Usar nota de crédito
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $refundService->useCreditNote(
                $input['credit_note_id'] ?? 0,
                $input['amount_to_use'] ?? 0,
                $input['order_id'] ?? 0
            );
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    AppLogger::error("Refunds API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
