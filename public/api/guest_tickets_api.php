<?php
/**
 * API para Gestión de Tickets de Invitados (sin registrarse)
 * Endpoint: /api/guest_tickets_api.php
 */

require_once '../../config/config.php';
require_once '../../backend/models/SalesTicket.php';

header('Content-Type: application/json');

// Solo administradores y personal pueden gestionar
require_admin();

// Validar CSRF para solicitudes de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_csrf_token();
}

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$pdo = $GLOBALS['pdo'];
$ticketModel = new SalesTicket($pdo);

try {
    // 1. Ejecutar expiración automática de tickets pendientes vencidos
    $pdo->exec("
        UPDATE sales_tickets 
        SET pickup_status = 'expired', updated_at = NOW() 
        WHERE pickup_status = 'pending' 
          AND expiration_date < NOW()
    ");

    switch ($action) {
        // Listar tickets de invitados
        case 'list':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $query = sanitize($_GET['query'] ?? '');
            $status = sanitize($_GET['status'] ?? 'all');
            
            $where = "WHERE u.role = 'guest' AND st.deleted_at IS NULL";
            $params = [];

            // Filtro por estado
            if ($status === 'pending') {
                $where .= " AND st.pickup_status = 'pending'";
            } elseif ($status === 'picked_up') {
                $where .= " AND st.pickup_status = 'picked_up'";
            } elseif ($status === 'expired') {
                $where .= " AND st.pickup_status = 'expired'";
            }

            // Filtro por búsqueda multicriterio
            if (!empty($query)) {
                $where .= " AND (st.folio ILIKE :query OR u.first_name ILIKE :query OR u.last_name ILIKE :query OR u.email ILIKE :query OR u.phone ILIKE :query OR o.order_number ILIKE :query)";
                $params[':query'] = '%' . $query . '%';
            }

            $stmt = $pdo->prepare("
                SELECT 
                    st.id,
                    st.folio,
                    st.description,
                    st.order_id,
                    o.order_number,
                    st.total_amount,
                    st.payment_status,
                    st.issued_date,
                    st.expiration_date,
                    st.pickup_status,
                    st.pickup_date,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone
                FROM sales_tickets st
                JOIN users u ON st.user_id = u.id
                LEFT JOIN orders o ON st.order_id = o.id
                $where
                ORDER BY st.issued_date DESC
            ");
            
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode(['success' => true, 'tickets' => $tickets]);
            break;

        // Obtener detalles del ticket (productos y log)
        case 'details':
            if ($method !== 'GET') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($_GET['folio'] ?? '');
            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio requerido']);
                exit;
            }

            $ticket = $ticketModel->getTicketWithPickupInfo($folio);
            if (!$ticket) {
                echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
                exit;
            }

            // Obtener logs de auditoría
            $logsResult = $ticketModel->getPickupLog($ticket['id']);
            $logs = $logsResult['success'] ? $logsResult['logs'] : [];

            echo json_encode([
                'success' => true, 
                'ticket' => $ticket,
                'logs' => $logs
            ]);
            break;

        // Validar recolección (confirmar entrega física)
        case 'validate':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($input['folio'] ?? '');
            $notes = sanitize($input['notes'] ?? 'Entrega de invitado confirmada por administrador');
            $adminId = $_SESSION['user_id'];

            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio requerido']);
                exit;
            }

            $result = $ticketModel->validatePickup($folio, $adminId, $notes);
            echo json_encode($result);
            break;

        // Reactivar un ticket expirado por 30 días adicionales
        case 'reactivate':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $ticketId = (int)($input['ticket_id'] ?? 0);
            $notes = sanitize($input['notes'] ?? 'Reactivación de ticket de invitado por administrador');
            $adminId = $_SESSION['user_id'];

            if ($ticketId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de ticket inválido']);
                exit;
            }

            $result = $ticketModel->reactivateTicket($ticketId, $adminId, $notes);
            echo json_encode($result);
            break;

        // Cancelar o eliminar ticket (soft-delete)
        case 'cancel':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $folio = sanitize($input['folio'] ?? '');
            $reason = sanitize($input['reason'] ?? 'Cancelado por administrador');
            $adminId = $_SESSION['user_id'];

            if (empty($folio)) {
                echo json_encode(['success' => false, 'message' => 'Folio requerido']);
                exit;
            }

            // Obtener ticket
            $stmt = $pdo->prepare("SELECT id FROM sales_tickets WHERE folio = :folio AND deleted_at IS NULL");
            $stmt->execute([':folio' => $folio]);
            $ticketRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticketRow) {
                echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
                exit;
            }

            // Marcar como cancelado/eliminado
            $delStmt = $pdo->prepare("UPDATE sales_tickets SET deleted_at = NOW(), pickup_status = 'cancelled', updated_at = NOW() WHERE folio = :folio");
            $delStmt->execute([':folio' => $folio]);

            // Log de auditoría
            $logStmt = $pdo->prepare("INSERT INTO ticket_pickup_log (ticket_id, admin_id, action, notes, ip_address, created_at) VALUES (:ticket_id, :admin_id, 'deleted', :notes, :ip_address, NOW())");
            $logStmt->execute([
                ':ticket_id' => $ticketRow['id'],
                ':admin_id'  => $adminId,
                ':notes'     => $reason,
                ':ip_address'=> $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            echo json_encode(['success' => true, 'message' => 'Ticket cancelado correctamente']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }

} catch (Exception $e) {
    error_log('Error en guest_tickets_api.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
